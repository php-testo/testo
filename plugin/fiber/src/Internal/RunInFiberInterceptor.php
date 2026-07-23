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
 * - {@see runTest()} (method-level) wraps a single test in its own fiber. When the case is already
 *   scheduling (a class-level `#[RunInFiber]`), it is a pass-through to avoid double-wrapping.
 *
 * Sits at {@see InterceptorOptions::ORDER_CLOSE_TO_TEST} — the innermost interceptor, so lifecycle/DI
 * still wrap the whole case while only the test loop / test body moves onto a fiber.
 *
 * @internal
 * @psalm-internal Testo\Fiber
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST, onConflict: ConflictPolicy::Last)]
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
        if (Scheduler::active()) {
            return $next($info);
        }

        // Method-level #[RunInFiber] (no class scheduling): run this one test in its own fiber.
        $fiber = new \Fiber(static fn(): TestResult => $next($info));
        $errors = Scheduler::run([$fiber], Schedule::Solo);

        \array_key_exists(0, $errors) and throw $errors[0];

        /** @var TestResult */
        return $fiber->getReturn();
    }
}
