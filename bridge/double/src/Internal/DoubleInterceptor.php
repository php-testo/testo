<?php

declare(strict_types=1);

namespace Testo\Bridge\Double\Internal;

use JMac\Testing\Double;
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
 * in a `finally` block: unmet `expects()` and deferred `received()` assertions fail the test. `verifyAll()`
 * returns how many checks it performed, which the bridge reports to the Assert plugin so a double-only test
 * still counts as making assertions. It always drains Double's global state, so an unmet expectation fails
 * an otherwise-passing test while an already-failed result is left alone.
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
                $verified = Double::verifyAll();
                $verified > 0 and self::reportVerifiedChecks($verified);
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

        return $result;
    }

    /**
     * Record the `$count` passed Double checks as one fulfilled assertion on the current test, so a test
     * whose only checks are `expects()` / `received()` verifications is not flagged as making no assertions.
     *
     * No-op without the Assert plugin (the {@see \class_exists()} guard). Runs innermost, before the
     * Assert plugin reads the history, so the record lands on the current test.
     */
    private static function reportVerifiedChecks(int $count): void
    {
        if (!\class_exists(StaticState::class)) {
            return;
        }

        $state = StaticState::current();
        $state === null or $state->history[] = new ExpectationFulfilled(
            \sprintf('%d Double %s verified', $count, $count === 1 ? 'check was' : 'checks were'),
            '',
        );
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
