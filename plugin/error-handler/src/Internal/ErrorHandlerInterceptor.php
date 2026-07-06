<?php

declare(strict_types=1);

namespace Testo\ErrorHandler\Internal;

use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\ErrorHandler\CapturedError;
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

        \set_error_handler(
            static function (int $severity, string $message, string $file, int $line) use (&$errors): bool {
                $errors[] = new CapturedError($severity, $message, $file, $line);
                return true;
            },
        );

        try {
            $result = $next($info);
        } finally {
            \restore_error_handler();
        }

        if ($errors === []) {
            return $result;
        }

        $result = $result->withAttribute(CapturedErrors::class, new CapturedErrors($errors));

        if ($this->failOnError && !$result->status->isFailure()) {
            $first = $errors[0];
            $result = $result
                ->with(status: Status::Failed)
                ->withFailure(new \ErrorException($first->message, 0, $first->severity, $first->file, $first->line));
        }

        return $result;
    }
}
