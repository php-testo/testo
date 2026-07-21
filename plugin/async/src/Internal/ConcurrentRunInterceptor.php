<?php

declare(strict_types=1);

namespace Testo\Async\Internal;

use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Internal\Runner\TestRunner;
use Testo\Async\Concurrent;
use Testo\Async\Strategy;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Event\TestCase\TestCaseFinished;
use Testo\Event\TestCase\TestCaseStarting;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestCaseRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;

/**
 * Runs a {@see Concurrent} test case under a scheduling {@see Strategy}.
 *
 * - `Sequential` is a pass-through — the case runs in Testo's default order via `$next`.
 * - `RoundRobin` / `Random` replace the sequential case loop with a cooperative {@see Scheduler}: each
 *   test runs in its own fiber and the scheduler interleaves them at
 *   {@see \Testo\Async\Coroutine::reschedule()} points. This runs on plain fibers (no Revolt), so tests
 *   interleave to shake out order-dependent races; awaiting real async work inside an interleaved test
 *   is unsupported — use `#[RunInCoroutine]` for that.
 *
 * Sits at {@see InterceptorOptions::ORDER_CLOSE_TO_TEST}, i.e. the innermost case interceptor (just
 * outside `CaseRunner::run`), so the lifecycle/DI case interceptors still wrap the whole case while the
 * interleaving strategies replace only the test loop (re-emitting the case events themselves).
 *
 * @internal
 * @psalm-internal Testo\Async
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST, onConflict: ConflictPolicy::Last)]
final readonly class ConcurrentRunInterceptor implements TestCaseRunInterceptor
{
    public function __construct(
        private Concurrent $options,
        private TestRunner $testRunner,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[\Override]
    public function runTestCase(CaseInfo $info, callable $next): CaseResult
    {
        // Sequential is Testo's default order — nothing to schedule, run the case as-is.
        if ($this->options->strategy === Strategy::Sequential) {
            return $next($info);
        }

        $this->eventDispatcher->dispatch(new TestCaseStarting($info));

        // One fiber per test, each running the full per-test pipeline; the scheduler interleaves them.
        $infos = [];
        $fibers = [];
        foreach ($info->definition->tests->getTests() as $name => $testDefinition) {
            $testInfo = new TestInfo(name: $name, caseInfo: $info, testDefinition: $testDefinition);
            $infos[] = $testInfo;
            $fibers[] = new \Fiber(fn(): TestResult => $this->testRunner->runTest($testInfo));
        }

        $errors = Scheduler::run($fibers, $this->options->strategy);

        $results = [];
        $status = Status::Passed;
        foreach ($fibers as $i => $fiber) {
            // A test whose pipeline threw outright has an unknown verdict — surface it as an errored
            // test and fail the case, mirroring CaseRunner::run's per-test catch.
            if (\array_key_exists($i, $errors)) {
                $status = Status::Error;
                $results[] = new TestResult(
                    info: $infos[$i],
                    status: Status::Error,
                    failure: $errors[$i],
                    summary: Summary::forTest(Status::Error),
                );
                continue;
            }

            /** @var TestResult $result */
            $result = $fiber->getReturn();
            $result->status->isFailure() || $result->status === Status::Aborted and ($status = Status::Failed);
            $results[] = $result;
        }

        $result = new CaseResult(
            results: $results,
            status: $status,
            summary: Summary::combine(\array_map(static fn(TestResult $r): Summary => $r->summary, $results)),
        );

        $this->eventDispatcher->dispatch(new TestCaseFinished($info, $result));
        return $result;
    }
}
