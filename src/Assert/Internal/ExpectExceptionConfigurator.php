<?php

declare(strict_types=1);

namespace Testo\Assert\Internal;

use Testo\Assert\Exception\StateNotFound;
use Testo\Assert\ExpectException;
use Testo\Assert\Internal\Expectation\ExpectExceptionHandler;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * Configures expected exceptions for a test based on the {@see ExpectException} attribute.
 *
 * @internal
 * @psalm-internal Testo\Assert
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_ASSERTIONS + 10)]
final readonly class ExpectExceptionConfigurator implements TestRunInterceptor
{
    public function __construct(
        private ExpectException $options,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $context = StaticState::current() ?? throw new StateNotFound();

        $context->expectations[] = ExpectExceptionHandler::createEquals($this->options->class);

        return $next($info);
    }
}
