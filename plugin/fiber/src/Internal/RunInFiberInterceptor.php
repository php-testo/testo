<?php

declare(strict_types=1);

namespace Testo\Fiber\Internal;

use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestCaseRunInterceptor;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;

/**
 * Runs {@see RunInFiber} tests on Testo's cooperative fiber {@see Scheduler}.
 *
 * Two levels, one attribute:
 * - {@see runTestCase()} (class-level) sets a {@see FiberTestBatchRunner} on {@see CaseInfo::$batchRunner};
 *   `CaseRunner` reads it there and drives the whole case's batch on fibers per the class-level
 *   {@see Schedule}. No container swapping, no re-emitted events.
 * - {@see runTest()} (method-level) wraps a single test in its own fiber. When a scheduler is already
 *   driving (a class-level `#[RunInFiber]`), it is a pass-through to avoid double-wrapping.
 *
 * Sits **outer** to the fiber-aware scoped-state guards (order just outside {@see
 * InterceptorOptions::ORDER_DATA_PROVIDER}): the method-level fiber wraps the whole per-test pipeline —
 * assertion collector, messenger scope, the test body — so the guards run *inside* the fiber. Each guard
 * swaps its per-test state out at every suspension it relays and back in on resumption, so each test
 * reads its own scoped state even while several interleave; a data-driven/retried test runs all its
 * datasets/attempts in its single fiber (data provider stays inner to the wrap).
 *
 * The test's coroutine scope ({@see \Testo\Fiber\Coroutine}) is *not* opened here — that is
 * {@see CoroutineScopeInterceptor}, wired by the same attribute at the innermost position, so spawned
 * coroutines run inside the guards and read their test's scoped state.
 *
 * @internal
 * @psalm-internal Testo\Fiber
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_DATA_PROVIDER - 1, onConflict: ConflictPolicy::Last)]
final readonly class RunInFiberInterceptor implements TestCaseRunInterceptor, TestRunInterceptor
{
    public function __construct(
        private RunInFiber $options,
    ) {}

    #[\Override]
    public function runTestCase(CaseInfo $info, callable $next): CaseResult
    {
        // Set the fiber batch runner on the case; CaseRunner picks it up and drives the whole batch on
        // the scheduler per the class-level Schedule.
        return $next($info->withBatchRunner(new FiberTestBatchRunner($this->options->schedule)));
    }

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        // Under a class-level #[RunInFiber] the batch runner already runs this test inside a scheduled
        // fiber — don't wrap it again.
        if (Scheduler::current() !== null) {
            return $next($info);
        }

        // Method-level #[RunInFiber] (no class scheduling): run this one test in its own fiber.
        $scheduler = new Scheduler(Schedule::Solo);
        $task = $scheduler->spawn(static fn(): TestResult => $next($info));
        $scheduler->drive();

        $task->error === null or throw $task->error;

        /** @var TestResult */
        return $task->result;
    }
}
