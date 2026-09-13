<?php

declare(strict_types=1);

namespace Testo\ErrorHandler\Internal;

use Testo\Common\Reflection;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Core\Log\MessageLog;
use Testo\Core\Value\Status;
use Testo\ErrorHandler\CapturedErrors;
use Testo\ErrorHandler\Exception\ErrorHandlerUnchanged;
use Testo\ErrorHandler\ExpectErrorHandlerChange;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * Intercepts PHP errors raised during test execution.
 *
 * Installs a capturing error handler for the duration of the test and forwards every error to the
 * handler that was active before. Errors silenced with `@` or excluded by `error_reporting()` are
 * forwarded but not captured. Captured errors are stored in the returned {@see TestResult} as a
 * {@see CapturedErrors} attribute.
 *
 * A passing test that leaves the handler stack changed is reported {@see Status::Risky} unless it
 * carries {@see ExpectErrorHandlerChange}; a test that carries it and leaves the stack unchanged
 * fails. The stack is restored either way.
 *
 * @internal
 * @psalm-internal Testo\ErrorHandler
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST)]
final readonly class ErrorHandlerInterceptor implements TestRunInterceptor
{
    private const CHANNEL = 'error-handler';

    /**
     * @param bool $failOnError When true, any captured error upgrades a passing test to
     *        {@see Status::Failed} with the first error wrapped in an {@see \ErrorException} as the failure.
     */
    public function __construct(
        private bool $failOnError = false,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $scope = new HandlerScope();
        $result = $this->run($info, $next, $scope);

        if ($result->status === Status::Passed) {
            $declared = self::declaresHandlerChange($info);
            if ($declared && !$scope->changed()) {
                $result = $result->with(status: Status::Failed)->withFailure(new ErrorHandlerUnchanged());
            } elseif (!$declared && $scope->changed()) {
                $result = $result
                    ->with(status: Status::Risky)
                    ->withMessages(new MessageLog([
                        ...$result->messages->all(),
                        new Message(\microtime(true), self::CHANNEL, Level::Warning, $scope->removed()
                            ? 'Test code or tested code removed error handlers other than its own.'
                            : 'Test code or tested code did not remove its own error handlers.'),
                    ]));
            }
        }

        if ($scope->errors === []) {
            return $result;
        }

        $result = $result->withAttribute(CapturedErrors::class, new CapturedErrors($scope->errors));

        if ($this->failOnError && $result->status === Status::Passed) {
            $first = $scope->errors[0];
            $result = $result
                ->with(status: Status::Failed)
                ->withFailure(new \ErrorException($first->message, 0, $first->severity, $first->file, $first->line));
        }

        return $result;
    }

    private static function declaresHandlerChange(TestInfo $info): bool
    {
        if (Reflection::fetchFunctionAttributes(
            $info->testDefinition->reflection,
            attributeClass: ExpectErrorHandlerChange::class,
        ) !== []) {
            return true;
        }

        $class = $info->caseInfo->definition->reflection;

        return $class !== null && Reflection::fetchClassAttributes(
            $class,
            attributeClass: ExpectErrorHandlerChange::class,
        ) !== [];
    }

    /**
     * Inside a fiber the scope leaves the stack on every suspension and comes back on resumption,
     * so errors fired by an interleaved sibling test never land here.
     *
     * @param callable(TestInfo): TestResult $next
     */
    private function run(TestInfo $info, callable $next, HandlerScope $scope): TestResult
    {
        $scope->install();
        try {
            if (\Fiber::getCurrent() === null) {
                return $next($info);
            }

            $fiber = new \Fiber(static fn(): TestResult => $next($info));
            $value = $fiber->start();
            while (!$fiber->isTerminated()) {
                $scope->suspend();
                try {
                    $resume = \Fiber::suspend($value);
                } catch (\Throwable $e) {
                    $scope->resume();
                    $value = $fiber->throw($e);
                    continue;
                }

                $scope->resume();
                $value = $fiber->resume($resume);
            }

            /** @var TestResult $result */
            $result = $fiber->getReturn();
            return $result;
        } finally {
            $scope->release();
        }
    }
}
