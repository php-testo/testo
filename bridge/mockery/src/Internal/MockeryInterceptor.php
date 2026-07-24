<?php

declare(strict_types=1);

namespace Testo\Bridge\Mockery\Internal;

use Mockery;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\State\Expectation\ExpectationFailed;
use Testo\Assert\State\Expectation\ExpectationFulfilled;
use Testo\Bridge\Mockery\MockeryConcurrencyException;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * Calls {@see Mockery::close()} in a `finally` block after every test: unfulfilled expectations
 * fail the test and the container is cleared between tests. Verified expectations are also reported
 * to the Assert plugin as assertions, mirroring PHPUnit's `MockeryPHPUnitIntegration`.
 *
 * Runs innermost so the teardown fires as close as possible to the test function.
 *
 * Mockery's mock container is process-global static state, so — unlike Testo's own fiber-local guards —
 * it cannot be isolated per fiber. This guard therefore serializes: it runs the test directly (no fiber
 * trampoline, so it cooperates with a real event loop) and, if another Mockery test is already in flight,
 * refuses to start with a {@see MockeryConcurrencyException}. That only happens under interleaving
 * ({@see \Testo\Fiber\Schedule::RoundRobin}/`Random`, {@see \Testo\Bridge\Revolt\Strategy::PerCase}),
 * where siblings would clobber each other's mocks. One-at-a-time execution — `Solo`/`PerTest` or no
 * fibers — is fully supported, including across a real event-loop suspension.
 *
 * @internal
 * @psalm-internal Testo\Bridge\Mockery
 */
#[InterceptorOptions(
    order: InterceptorOptions::ORDER_CLOSE_TO_TEST,
    testType: TestType::Test,
)]
final class MockeryInterceptor implements TestRunInterceptor
{
    /**
     * Whether a Mockery-guarded test is currently running. The container is process-global, so a second
     * test starting while this is set means two tests are interleaving over the same container.
     */
    private static bool $running = false;

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        self::$running and throw new MockeryConcurrencyException();
        self::$running = true;

        $result = null;
        try {
            $result = $next($info);
        } finally {
            self::$running = false;

            # close() clears the container, so read the count first.
            $verified = \Mockery::getContainer()->mockery_getExpectationCount();
            try {
                \Mockery::close();
                $verified > 0 and self::reportVerifiedExpectations($verified);
            } catch (\Throwable $e) {
                # Record the unmet expectation on the test, then turn it into a normal failure — an
                # exception escaping here would abort the pipeline (Status::Aborted) instead. Leave an
                # already-failed result alone; a null $result means $next() threw — let it propagate.
                $failure = self::reportFailedExpectation($e);
                $result?->status === Status::Passed and $result = $result
                    ->with(status: Status::Failed)
                    ->withFailure($failure ?? $e);
            }
        }

        return $result;
    }

    /**
     * Record the `$count` verified Mockery expectations as one fulfilled assertion on the current test.
     *
     * No-op without the Assert plugin (the {@see \class_exists()} guard). Runs innermost, before the
     * Assert plugin reads the history, so the record lands on the current test.
     */
    private static function reportVerifiedExpectations(int $count): void
    {
        if (!\class_exists(StaticState::class)) {
            return;
        }

        $state = StaticState::current();
        $state === null or $state->history[] = new ExpectationFulfilled(
            \sprintf('%d Mockery %s fulfilled', $count, $count === 1 ? 'expectation was' : 'expectations were'),
            '',
        );
    }

    /**
     * Record an unmet Mockery expectation as a failed assertion on the current test and return it,
     * so the caller can use the same record as the test's failure (mirrors {@see \Testo\Assert\Internal\Expectation\NotLeaks}).
     *
     * Returns null without the Assert plugin — the caller then falls back to the raw Mockery exception.
     */
    private static function reportFailedExpectation(\Throwable $e): ?ExpectationFailed
    {
        if (!\class_exists(StaticState::class)) {
            return null;
        }

        $failure = new ExpectationFailed(
            expectation: 'the Mockery expectations are fulfilled',
            context: '',
            reason: $e->getMessage(),
            details: '',
        );

        $state = StaticState::current();
        $state === null or $state->history[] = $failure;

        return $failure;
    }
}
