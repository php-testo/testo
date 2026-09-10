<?php

declare(strict_types=1);

namespace Testo\Pipeline\Middleware;

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Pipeline\Interceptor;

/**
 * Intercept composing the list of test suites to run.
 *
 * Runs once per session, before any suite is scanned. The core logic yields the configured suites;
 * an interceptor may drop, reorder, narrow, multiply, or add suites on the way back.
 *
 * @extends Interceptor<ApplicationConfig, list<SuiteConfig>>
 *
 * @api
 */
interface SuiteLocatorInterceptor extends Interceptor
{
    /**
     * @param ApplicationConfig $config Application configuration the suites are composed from.
     * @param callable(ApplicationConfig): list<SuiteConfig> $next Next interceptor or core logic.
     * @return list<SuiteConfig> Suites to run, in order.
     */
    public function locateTestSuites(ApplicationConfig $config, callable $next): array;
}
