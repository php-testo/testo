<?php

declare(strict_types=1);

namespace Testo\Fiber\Internal;

use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Internal\Runner\TestRunner;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Event\TestCase\TestCaseFinished;
use Testo\Event\TestCase\TestCaseStarting;
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
 * - {@see runTestCase()} (class-level) replaces the case's test loop with the scheduler: one fiber per
 *   test running the full per-test pipeline, driven per {@see Schedule} (`Solo` to completion, or
 *   `RoundRobin` / `Random` interleaved). It re-emits the TestCase events and aggregates the
 *   {@see CaseResult} like `CaseRunner::run`.
 * - {@see runTest()} (method-level) wraps a single test in its own fiber. When the case interceptor is
 *   already scheduling (a class-level `#[RunInFiber]`), it is a pass-through to avoid double-wrapping.
 *
 * Sits at {@see InterceptorOptions::ORDER_CLOSE_TO_TEST} — the innermost interceptor, so lifecycle/DI
 * still wrap the whole case while only the test loop / test body moves onto a fiber.
 *
 * @internal
 * @psalm-internal Testo\Fiber
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST, onConflict: ConflictPolicy::Last)]
final readonly class RunInFiberInterceptor implements TestRunInterceptor, TestCaseRunInterceptor
{
    public function __construct(
        private RunInFiber $options,
        private TestRunner $testRunner,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        // Under a class-level #[RunInFiber] the case interceptor already runs this test inside a
        // scheduled fiber — don't wrap it again.
        if (Scheduler::active()) {
            return $next($info);
        }

        // Method-level #[RunInFiber] (no class scheduling): run this one test in its own fiber.
        $fiber = new \Fiber(static fn(): TestResult => $next($info));
        $errors = Scheduler::run([$fiber], Schedule::Solo);

        if (\array_key_exists(0, $errors)) {
            throw $errors[0];
        }

        /** @var TestResult */
        return $fiber->getReturn();
    }

    #[\Override]
    public function runTestCase(CaseInfo $info, callable $next): CaseResult
    {
        $this->eventDispatcher->dispatch(new TestCaseStarting($info));

        // One fiber per test, each running the full per-test pipeline; the scheduler drives them.
        $infos = [];
        $fibers = [];
        foreach ($info->definition->tests->getTests() as $name => $testDefinition) {
            $testInfo = new TestInfo(name: $name, caseInfo: $info, testDefinition: $testDefinition);
            $infos[] = $testInfo;
            $fibers[] = new \Fiber(fn(): TestResult => $this->testRunner->runTest($testInfo));
        }

        $errors = Scheduler::run($fibers, $this->options->schedule);

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
