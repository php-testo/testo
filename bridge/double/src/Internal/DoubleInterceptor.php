<?php

declare(strict_types=1);

namespace Testo\Bridge\Double\Internal;

use JMac\Testing\AutoVerifySnapshot;
use JMac\Testing\CheckEvent;
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
 * Bridges Double's auto-verification into a Testo test.
 *
 * Turns on {@see Double::enableAutoVerify()} before the test body and runs {@see Double::verifyAll()}
 * afterwards in a `finally`: unmet `expects()` and deferred `received()` assertions are checked there, and
 * a failure turns an otherwise-passing test into a failed one (an already-failed result is left alone).
 *
 * A {@see Double::listen()} listener mirrors every check into the Assert plugin's history the moment it
 * resolves, pass or fail, immediate call-time failures included. So a double-only test still counts as
 * making assertions, and the report shows what was checked in the order it happened. The listener only
 * records; it never changes the result, so whether a body-thrown check failure fails the test or is
 * absorbed by `#[ExpectException]` stays the rest of the pipeline's call.
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
        self::ensureListening();

        # Enable before the test body runs so every double it creates is collected for verification.
        Double::enableAutoVerify();

        $result = null;
        try {
            $result = $this->run($info, $next);
        } finally {
            try {
                Double::verifyAll();
            } catch (\Throwable $e) {
                # The unmet expectation was already recorded by the listener. Turn it into a normal failure
                # here — an exception escaping would abort the pipeline (Status::Aborted) instead. Leave an
                # already-failed result alone; a null $result means $next() threw, let it propagate.
                $result?->status === Status::Passed and $result = $result
                    ->with(status: Status::Failed)
                    ->withFailure($e);
            }
        }

        return $result;
    }

    /**
     * Register the check recorder with Double once per process. Double's listener registry is process-wide
     * and long-lived by design, so registering per test would pile up duplicates. No-op without the Assert
     * plugin: there is no history to write to, and {@see Double::verifyAll()} still fails tests on its own.
     */
    private static function ensureListening(): void
    {
        static $listening = false;
        if ($listening || !\class_exists(StaticState::class)) {
            return;
        }

        $listening = true;
        Double::listen(self::record(...));
    }

    /**
     * Mirror one resolved Double check into the current test's assertion history: a fulfilled record when
     * it passed, a failed one carrying the diagnostic when it did not.
     */
    private static function record(CheckEvent $event): void
    {
        $state = StaticState::current();
        if ($state === null) {
            return;
        }

        $subject = $event->method === null
            ? \sprintf('Double `%s`', $event->label)
            : \sprintf('Double `%s`->%s()', $event->label, $event->method);

        $state->history[] = $event->passed
            ? new ExpectationFulfilled($subject . ' passed its check', '')
            : new ExpectationFailed(
                expectation: $subject . ' passed its check',
                context: '',
                reason: $event->failure?->getMessage() ?? '',
                details: '',
            );
    }

    /**
     * Run the test, keeping this test's pending doubles bound to it across fiber suspensions.
     *
     * Double's pending doubles live in process-global state, so under concurrent (fiber-based) execution
     * sibling tests would sweep each other's doubles into the wrong teardown. On every suspension we park
     * this test's state with {@see Double::pauseAutoVerify()} and hand a fresh slate to the sibling; on
     * resumption we reinstall it with {@see Double::resumeAutoVerify()}.
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
            $snapshot = Double::pauseAutoVerify();
            try {
                $resume = \Fiber::suspend($value);
            } catch (\Throwable $e) {
                Double::resumeAutoVerify($snapshot);
                $value = $fiber->throw($e);
                continue;
            }

            Double::resumeAutoVerify($snapshot);
            $value = $fiber->resume($resume);
        }

        /** @var TestResult $result */
        $result = $fiber->getReturn();
        return $result;
    }
}
