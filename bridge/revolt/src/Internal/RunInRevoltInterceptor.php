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
 * - {@see Strategy::PerTest} (default): each test's whole pipeline is put on the loop by {@see runTest()},
 *   one test at a time, each to completion.
 * - {@see Strategy::PerCase}: the whole case is handed a {@see RevoltTestBatchRunner} (via
 *   {@see CaseInfo::withBatchRunner()}) that launches every test at once, so they run concurrently and
 *   interleave at their await points.
 *
 * Each test's pipeline is launched as a microtask and the current fiber blocks on a
 * {@see \Revolt\EventLoop\Suspension} until it completes, so the test may await real async work while
 * staying, from the outside, an ordinary blocking call.
 *
 * Sits **outer** to the fiber-aware scoped-state guards (order just outside {@see
 * InterceptorOptions::ORDER_DATA_PROVIDER}): the loop dispatch wraps the guards, so the whole per-test
 * pipeline — assertion collector, messenger scope, the test body — runs *inside* the loop fiber. The
 * guards hold their state per fiber (see {@see \Testo\Common\FiberLocal}), so each test reads its own
 * scoped state even while several tests interleave on one loop; a data-driven/retried test runs all its
 * datasets/attempts inside its single fiber (data provider stays inner to the dispatch).
 *
 * @internal
 * @psalm-internal Testo\Bridge\Revolt
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_DATA_PROVIDER - 1, onConflict: ConflictPolicy::Last)]
final readonly class RunInRevoltInterceptor implements TestCaseRunInterceptor, TestRunInterceptor
{
    public function __construct(
        private RunInRevolt $options,
    ) {}

    #[\Override]
    public function runTestCase(CaseInfo $info, callable $next): CaseResult
    {
        // Per-case: hand the case a runner that launches every test on the loop at once (concurrent).
        // Per-test leaves the case untouched and dispatches each test onto the loop individually, one at a
        // time, in runTest().
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

        // Strategy::PerTest (or a method-level attribute): put this test's whole pipeline on the loop, so
        // the fiber-local guards inner to us run in the same fiber as the test body.
        return RevoltTestBatchRunner::runOnLoop(static fn(): TestResult => $next($info));
    }
}
