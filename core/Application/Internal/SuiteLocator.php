<?php

declare(strict_types=1);

namespace Testo\Application\Internal;

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Filter;
use Testo\Pipeline\InterceptorProvider;
use Testo\Pipeline\Middleware\SuiteLocatorInterceptor;
use Testo\Pipeline\Pipeline;
use Testo\Pipeline\PipeOptions;

/**
 * Composes the list of test suites to run through the {@see SuiteLocatorInterceptor} pipeline.
 *
 * @internal
 * @psalm-internal Testo\Application
 */
final readonly class SuiteLocator
{
    public function __construct(
        private InterceptorProvider $interceptorProvider,
        private Filter $filter,
    ) {}

    /**
     * @return list<SuiteConfig>
     */
    public function locate(ApplicationConfig $config): array
    {
        $interceptors = $this->interceptorProvider->fromConfig(SuiteLocatorInterceptor::class);

        /**
         * @see SuiteLocatorInterceptor::locateTestSuites()
         * @var callable(ApplicationConfig): array<array-key, SuiteConfig> $pipeline
         */
        $pipeline = Pipeline::prepare(
            new PipeOptions(includeTypes: $this->filter->type, excludeTypes: $this->filter->notType),
            ...$interceptors,
        )
            ->with(static fn(ApplicationConfig $config): array => $config->suites, 'locateTestSuites');

        # An interceptor that filters with `array_filter()` hands back a keyed array; consumers count
        # positions in this list, so the keys are dropped here once for all of them.
        return \array_values($pipeline($config));
    }
}
