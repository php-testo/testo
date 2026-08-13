<?php

declare(strict_types=1);

namespace Testo\ErrorHandler\Internal;

use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\ErrorHandler\CapturedError;
use Testo\ErrorHandler\CapturedErrors;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * Intercepts PHP errors raised during test execution.
 *
 * Registers a custom error handler via {@see \set_error_handler()} before the test runs and
 * restores the previous handler afterward via {@see \restore_error_handler()}. Accumulated
 * errors are stored in the returned {@see TestResult} as a {@see CapturedErrors} attribute.
 *
 * @internal
 * @psalm-internal Testo\ErrorHandler
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST)]
final readonly class ErrorHandlerInterceptor implements TestRunInterceptor
{
    /**
     * @param bool $failOnError When true, any captured error upgrades a passing test to
     *                          {@see Status::Failed} with the first error wrapped in an
     *                          {@see \ErrorException} as the failure.
     */
    public function __construct(
        private bool $failOnError = false,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        /** @var list<CapturedError> $errors */
        $errors = [];

        $handler = static function (int $severity, string $message, string $file, int $line) use (&$errors): bool {
            $errors[] = new CapturedError($severity, $message, $file, $line);
            return true;
        };

        $result = $this->run($info, $next, $handler);

        if ($errors === []) {
            return $result;
        }

        $result = $result->withAttribute(CapturedErrors::class, new CapturedErrors($errors));

        if ($this->failOnError && $result->status === Status::Passed) {
            $first = $errors[0];
            $result = $result
                ->with(status: Status::Failed)
                ->withFailure(new \ErrorException($first->message, 0, $first->severity, $first->file, $first->line));
        }

        return $result;
    }

    /**
     * Runs the test with {@see $handler} installed via {@see \set_error_handler()}, keeping it
     * bound to this test across fiber suspensions.
     *
     * set_error_handler()/restore_error_handler() operate on one process-global stack, so under
     * concurrent (fiber-based) execution — where sibling tests interleave with this one — a plain
     * install-before/restore-after around $next() would leak errors into the wrong test's
     * CapturedErrors, and an interleaved resume could pop a sibling's handler instead of ours. On
     * every suspension we restore whichever handler was active before this test installed its own
     * (the native stack does that for free); on resumption we re-install this test's handler.
     * Mirrors {@see \Testo\Bridge\Mockery\Internal\MockeryInterceptor::run()} and
     * {@see \Testo\Application\Internal\MessengerHub::scope()}.
     *
     * @param callable(TestInfo): TestResult $next
     */
    private function run(TestInfo $info, callable $next, \Closure $handler): TestResult
    {
        \set_error_handler($handler);
        try {
            if (\Fiber::getCurrent() === null) {
                return $next($info);
            }

            $fiber = new \Fiber(static fn(): TestResult => $next($info));
            $value = $fiber->start();
            while (!$fiber->isTerminated()) {
                \restore_error_handler();
                try {
                    $resume = \Fiber::suspend($value);
                } catch (\Throwable $e) {
                    \set_error_handler($handler);
                    $value = $fiber->throw($e);
                    continue;
                }

                \set_error_handler($handler);
                $value = $fiber->resume($resume);
            }

            /** @var TestResult $result */
            $result = $fiber->getReturn();
            return $result;
        } finally {
            \restore_error_handler();
        }
    }
}
