<?php

declare(strict_types=1);

namespace Testo\Bridge\Revolt\Internal;

use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Bridge\Revolt\Strategy;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestCaseRunInterceptor;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;

/**
 * Drives {@see RunInRevolt} tests as coroutines on the Revolt event loop, per the chosen
 * {@see Strategy}.
 *
 * - {@see Strategy::PerTest} (default): each test is put on the loop from *inside* the fiber-aware
 *   scoped-state guards ({@see runTest()}), so the guards stay on their synchronous main-fiber path and
 *   only the test body reaches the loop.
 * - {@see Strategy::PerCase}: the whole case is handed a {@see RevoltTestBatchRunner} (via
 *   {@see CaseInfo::withBatchRunner()}) that runs the batch on one loop run. This puts the guards inside
 *   a loop fiber and currently deadlocks — it is usable once the guards go fiber-local.
 *
 * Each test body is launched as a microtask and the current fiber blocks on a
 * {@see \Revolt\EventLoop\Suspension} until it completes, so the test may await real async work while
 * staying, from the outside, an ordinary blocking call.
 *
 * Sits at {@see InterceptorOptions::ORDER_CLOSE_TO_TEST}, i.e. the innermost case/test interceptor, so
 * under {@see Strategy::PerTest} the guards wrap the loop dispatch rather than the other way around.
 *
 * @internal
 * @psalm-internal Testo\Bridge\Revolt
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST, onConflict: ConflictPolicy::Last)]
final readonly class RunInRevoltInterceptor implements TestCaseRunInterceptor, TestRunInterceptor
{
    public function __construct(
        private RunInRevolt $options,
    ) {}

    #[\Override]
    public function runTestCase(CaseInfo $info, callable $next): CaseResult
    {
        // Per-case: drive the whole case on the loop. Per-test leaves the case untouched and dispatches
        // each test onto the loop individually in runTest().
        return $this->options->strategy === Strategy::PerCase
            ? $next($info->withBatchRunner(new RevoltTestBatchRunner()))
            : $next($info);
    }

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        // A case-level runner already drove us onto the loop (Strategy::PerCase) — just run the pipeline.
        if (RevoltTestBatchRunner::active()) {
            return $next($info);
        }

        // Strategy::PerTest (or a method-level attribute): put this single test on the loop, from inside
        // the guards.
        return RevoltTestBatchRunner::runOnLoop(static fn(): TestResult => $next($info));
    }
}
