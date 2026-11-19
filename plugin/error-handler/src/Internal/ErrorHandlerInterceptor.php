<?php

declare(strict_types=1);

namespace Testo\ErrorHandler\Internal;

use Testo\Common\Messenger;
use Testo\Common\Reflection;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Log\Level;
use Testo\Core\Value\Status;
use Testo\ErrorHandler\CapturedErrors;
use Testo\ErrorHandler\Exception\ErrorHandlerUnchanged;
use Testo\ErrorHandler\ExpectErrorHandlerChange;
use Testo\ErrorHandler\WithoutErrorHandler;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;

/**
 * Intercepts PHP errors raised during test execution.
 *
 * Installs a capturing error handler for the duration of the test and forwards every error to the
 * handler that was active before. Errors silenced with `@` or excluded by `error_reporting()` are
 * forwarded but not captured. Captured errors are stored in the returned {@see TestResult} as a
 * {@see CapturedErrors} attribute; those the previous handler did not take are also logged to the
 * `stderr` channel of the {@see Messenger} in place of PHP's own printing. `E_USER_ERROR` is thrown as an
 * {@see \ErrorException}.
 *
 * A passing test that leaves the handler stack changed is reported {@see Status::Risky} unless it
 * carries {@see ExpectErrorHandlerChange}; a test that carries it and leaves the stack unchanged
 * fails. The stack is restored either way.
 *
 * A test carrying {@see WithoutErrorHandler} runs untouched: no handler, no capture, no verdict.
 *
 * Of several instances in one pipeline the last one registered wins: a plugin configured for a suite
 * comes after the default instance and overrides it.
 *
 * @internal
 * @psalm-internal Testo\ErrorHandler
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST, onConflict: ConflictPolicy::Last)]
final readonly class ErrorHandlerInterceptor implements TestRunInterceptor
{
    private const CHANNEL = 'error-handler';

    /**
     * @param Messenger $messenger Receives the unhandled errors and the plugin's verdicts on the
     *        handler stack, attributed to the running test.
     * @param bool $failOnError When true, any captured error upgrades a passing test to
     *        {@see Status::Failed} with the first error wrapped in an {@see \ErrorException} as the failure.
     */
    public function __construct(
        private Messenger $messenger,
        private bool $failOnError = false,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        if (self::declares($info, WithoutErrorHandler::class)) {
            $result = $next($info);

            self::declares($info, ExpectErrorHandlerChange::class) and $this->messenger->log(
                Messenger::CHANNEL_STDERR,
                '#[ExpectErrorHandlerChange] has no effect together with #[WithoutErrorHandler]: no handler is installed, so no stack change is checked.',
                Level::Warning,
            );

            return $result;
        }

        $scope = new HandlerScope($this->messenger);
        $result = $this->run($info, $next, $scope);

        if ($result->status === Status::Passed) {
            $declared = self::declares($info, ExpectErrorHandlerChange::class);
            if ($declared && !$scope->changed()) {
                $result = $result->with(status: Status::Failed)->withFailure(new ErrorHandlerUnchanged());
            } elseif (!$declared && $scope->changed()) {
                $result = $result->with(status: Status::Risky);
                $this->messenger->log(self::CHANNEL, $scope->removed()
                    ? 'Test code or tested code removed error handlers other than its own.'
                    : 'Test code or tested code did not remove its own error handlers.', Level::Warning);
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

    /**
     * @param class-string $attribute
     */
    private static function declares(TestInfo $info, string $attribute): bool
    {
        if (Reflection::fetchFunctionAttributes(
            $info->testDefinition->reflection,
            attributeClass: $attribute,
        ) !== []) {
            return true;
        }

        $class = $info->caseInfo->definition->reflection;

        return $class !== null && Reflection::fetchClassAttributes(
            $class,
            attributeClass: $attribute,
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
