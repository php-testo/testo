<?php

declare(strict_types=1);

namespace Testo\ErrorHandler;

use Internal\Container\Container;
use Testo\Common\PluginConfigurator;
use Testo\ErrorHandler\Internal\ErrorHandlerInterceptor;
use Testo\Pipeline\InterceptorCollector;

/**
 * Plugin that captures PHP errors raised during test execution.
 *
 * By default errors are collected and stored as a {@see Internal\CapturedErrors} attribute
 * on the {@see \Testo\Core\Context\TestResult}, but the test still passes. Pass
 * {@see $failOnError}: true to make any captured error fail the test instead.
 *
 * @api
 */
final readonly class ErrorHandlerPlugin implements PluginConfigurator
{
    /**
     * @param bool $failOnError When true, a captured PHP error fails the test. When false
     *                          (default) errors are collected but the test result is unchanged.
     */
    public function __construct(
        private bool $failOnError = false,
    ) {}

    #[\Override]
    public function configure(Container $container): void
    {
        $container->get(InterceptorCollector::class)
            ->addInterceptor(new ErrorHandlerInterceptor($this->failOnError));
    }
}
