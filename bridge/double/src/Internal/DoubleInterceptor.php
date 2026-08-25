<?php

declare(strict_types=1);

namespace Testo\Bridge\Double\Internal;

use JMac\Testing\AutoVerifyScope;
use JMac\Testing\Double;
use JMac\Testing\Engine\DoubleState;
use JMac\Testing\Exceptions\ExpectationCallLimitExceededException;
use JMac\Testing\Exceptions\ExpectationCallMismatchException;
use JMac\Testing\Exceptions\OutOfOrderCallException;
use JMac\Testing\Exceptions\UnexpectedCallException;
use JMac\Testing\Exceptions\UnsatisfiedExpectationException;
use JMac\Testing\Exceptions\UnsatisfiedReceivedAssertionException;
use JMac\Testing\Exceptions\UnusedAssertionException;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\State\Expectation\ExpectationFailed;
use Testo\Assert\State\Expectation\ExpectationFulfilled;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * Arms {@see Double::armAutoVerify()} before every test and runs {@see Double::verifyAll()} afterwards
 * in a `finally` block: unmet `expects()` and deferred `received()` assertions fail the test. Before
 * verifying, the bridge snapshots the pending doubles and reports one fulfilled assertion per double
 * (with its recorded calls) to the Assert plugin, so a double-only test still counts as making assertions
 * and its report carries what was checked. It always drains Double's global state, so an unmet expectation
 * fails an otherwise-passing test while an already-failed result is left alone.
 *
 * A Double check that throws inside the test body (a failed `received()`, `unused()` or an unexpected
 * call on a strict double) is captured by the runner into the result's failure. The bridge records it in
 * the assertion history so the double's diagnostic stays visible, then passes the result through untouched:
 * whether the test ends up failed or passes because `#[ExpectException]` caught that throw is decided by
 * the rest of the pipeline, not here.
 *
 * Runs innermost so the teardown fires as close as possible to the test function.
 *
 * @internal
 * @psalm-internal Testo\Bridge\Double
 */
#[InterceptorOptions(
    order: InterceptorOptions::ORDER_CLOSE_TO_TEST,
    testType: TestType::Test,
)]
final readonly class DoubleInterceptor implements TestRunInterceptor
{
    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        # Arm before the test body runs so every double it creates is collected for verification.
        Double::armAutoVerify();

        $result = null;
        try {
            $result = $this->run($info, $next);
        } finally {
            try {
                # Snapshot the pending doubles before verifying so we can describe each in the test's
                # assertion history; restore it verbatim so verifyAll() checks exactly the same set.
                $scope = Double::captureAutoVerifyScope();
                $records = \class_exists(StaticState::class) ? self::describeVerified($scope) : [];
                Double::restoreAutoVerifyScope($scope);
                Double::verifyAll();
                $records === [] or self::pushHistory($records);
            } catch (\Throwable $e) {
                # Record the unmet expectation on the test, then turn it into a normal failure — an
                # exception escaping here would abort the pipeline (Status::Aborted) instead. Leave an
                # already-failed result alone; a null $result means $next() threw — let it propagate.
                $failure = self::reportFailedCheck($e);
                $result?->status === Status::Passed and $result = $result
                    ->with(status: Status::Failed)
                    ->withFailure($failure ?? $e);
            }
        }

        # A Double check that failed in the test body arrives as the result's failure. Record it, leaving
        # the result itself untouched so the pipeline still decides the final status (including a pass when
        # #[ExpectException] catches that throw).
        $result === null or self::reportBodyFailure($result);

        return $result;
    }

    /**
     * Every Double exception that represents a *check* failing, as opposed to a misuse error (calling an
     * unknown or static method, misconfiguring a mode). Only these are recorded as a failed check.
     */
    private const CHECK_FAILURES = [
        UnsatisfiedExpectationException::class,
        UnsatisfiedReceivedAssertionException::class,
        UnusedAssertionException::class,
        UnexpectedCallException::class,
        ExpectationCallLimitExceededException::class,
        ExpectationCallMismatchException::class,
        OutOfOrderCallException::class,
    ];

    /**
     * Build one fulfilled-assertion record per pending double (summarising the calls it recorded) plus one
     * for the deferred `received()` assertions, so a test whose only checks are Double verifications is not
     * flagged as making no assertions and its report shows what was verified.
     *
     * Reads only from the captured snapshot, never throws, and touches no reflection — `received()`
     * assertions expose no readable detail, so they collapse into a single count.
     *
     * @return list<ExpectationFulfilled>
     */
    private static function describeVerified(AutoVerifyScope $scope): array
    {
        $records = [];
        foreach ($scope->pending() as $state) {
            $records[] = new ExpectationFulfilled(self::describeDouble($state), '');
        }

        $received = \count($scope->pendingReceived());
        $received > 0 and $records[] = new ExpectationFulfilled(
            \sprintf('%d Double received %s verified', $received, $received === 1 ? 'assertion was' : 'assertions were'),
            '',
        );

        return $records;
    }

    /**
     * A one-line summary of a single double: its label and the methods it recorded calls to, with counts.
     */
    private static function describeDouble(DoubleState $state): string
    {
        $counts = [];
        foreach ($state->calls() as $call) {
            $counts[$call['method']] = ($counts[$call['method']] ?? 0) + 1;
        }

        if ($counts === []) {
            return \sprintf('Double `%s` verified with no recorded calls', $state->label());
        }

        $parts = [];
        foreach ($counts as $method => $times) {
            $parts[] = \sprintf('%s()×%d', $method, $times);
        }

        return \sprintf('Double `%s` verified (%s)', $state->label(), \implode(', ', $parts));
    }

    /**
     * Append the fulfilled records to the current test's assertion history.
     *
     * No-op without the Assert plugin (the {@see \class_exists()} guard). Runs innermost, before the
     * Assert plugin reads the history, so the records land on the current test.
     *
     * @param list<ExpectationFulfilled> $records
     */
    private static function pushHistory(array $records): void
    {
        if (!\class_exists(StaticState::class)) {
            return;
        }

        $state = StaticState::current();
        if ($state === null) {
            return;
        }

        foreach ($records as $record) {
            $state->history[] = $record;
        }
    }

    /**
     * Record an unmet Double expectation as a failed assertion on the current test and return it,
     * so the caller can use the same record as the test's failure.
     *
     * Returns null without the Assert plugin — the caller then falls back to the raw Double exception.
     */
    private static function reportFailedCheck(\Throwable $e): ?ExpectationFailed
    {
        if (!\class_exists(StaticState::class)) {
            return null;
        }

        $failure = new ExpectationFailed(
            expectation: 'the Double expectations are fulfilled',
            context: '',
            reason: $e->getMessage(),
            details: '',
        );

        $state = StaticState::current();
        $state === null or $state->history[] = $failure;

        return $failure;
    }

    /**
     * Record a Double check that failed inside the test body (surfaced as `$result->failure`) as a failed
     * assertion on the current test. The result is not modified: the failure is already there, and whether
     * it fails the test or is absorbed by `#[ExpectException]` is the pipeline's call.
     *
     * No-op unless the failure is a Double check failure (see {@see self::CHECK_FAILURES}) and the Assert
     * plugin is present.
     */
    private static function reportBodyFailure(TestResult $result): void
    {
        $failure = $result->failure;
        if ($failure === null || !self::isCheckFailure($failure) || !\class_exists(StaticState::class)) {
            return;
        }

        $state = StaticState::current();
        $state === null or $state->history[] = new ExpectationFailed(
            expectation: 'the Double checks passed',
            context: '',
            reason: $failure->getMessage(),
            details: '',
        );
    }

    private static function isCheckFailure(\Throwable $failure): bool
    {
        foreach (self::CHECK_FAILURES as $class) {
            if ($failure instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Run the test, keeping this test's pending doubles bound to it across fiber suspensions.
     *
     * Double's pending doubles live in process-global state, so under concurrent (fiber-based) execution
     * sibling tests would sweep each other's doubles into the wrong teardown. On every suspension we park
     * this test's state with {@see Double::captureAutoVerifyScope()} and hand a fresh slate to the sibling;
     * on resumption we reinstall it with {@see Double::restoreAutoVerifyScope()}.
     *
     * @param callable(TestInfo): TestResult $next
     */
    private function run(TestInfo $info, callable $next): TestResult
    {
        if (\Fiber::getCurrent() === null) {
            return $next($info);
        }

        $fiber = new \Fiber(static fn(): TestResult => $next($info));
        $value = $fiber->start();
        while (!$fiber->isTerminated()) {
            $scope = Double::captureAutoVerifyScope();
            try {
                $resume = \Fiber::suspend($value);
            } catch (\Throwable $e) {
                Double::restoreAutoVerifyScope($scope);
                $value = $fiber->throw($e);
                continue;
            }

            Double::restoreAutoVerifyScope($scope);
            $value = $fiber->resume($resume);
        }

        /** @var TestResult $result */
        $result = $fiber->getReturn();
        return $result;
    }
}
