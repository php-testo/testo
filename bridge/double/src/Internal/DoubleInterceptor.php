<?php

declare(strict_types=1);

namespace Testo\Bridge\Double\Internal;

use JMac\Testing\CheckEvent;
use JMac\Testing\Double;
use JMac\Testing\Exceptions\AmbiguousExpectationException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitAmbiguousExpectationException;
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
 * Bridges Double's auto-verification into a Testo test: unmet `expects()` and deferred `received()`
 * checks fail the test at teardown, and every resolved check is mirrored into the Assert plugin's
 * history so a double-only test counts as making assertions.
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
        Double::enableAutoVerify();

        $result = null;
        try {
            $result = $this->run($info, $next);
        } finally {
            # A null $result means $next() threw; that exception keeps propagating.
            $failed = $result?->status !== Status::Passed;
            try {
                Double::verifyAll(testFailed: $failed);
            } catch (\Throwable $e) {
                # An exception escaping would abort the pipeline (Status::Aborted), so a passed test turns
                # failed here. An already-failed test keeps its own failure: only an ambiguous expectation
                # is left to report, and Double emits no check event for it, so it is recorded by hand.
                if ($failed) {
                    self::recordAmbiguity($e);
                } else {
                    \assert($result !== null);
                    $result = $result->with(status: Status::Failed)->withFailure($e);
                }
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

    private static function recordAmbiguity(\Throwable $e): void
    {
        ($e instanceof AmbiguousExpectationException || $e instanceof PHPUnitAmbiguousExpectationException)
            and self::record(new CheckEvent($e->label, method: null, passed: false, failure: $e));
    }

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
