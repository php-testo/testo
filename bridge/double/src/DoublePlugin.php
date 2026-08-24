<?php

declare(strict_types=1);

namespace Testo\Bridge\Double;

use Internal\Container\Container;
use Testo\Bridge\Double\Internal\DoubleInterceptor;
use Testo\Common\PluginConfigurator;
use Testo\Pipeline\InterceptorCollector;

/**
 * Plugin that automatically verifies every {@see \JMac\Testing\Double} created during a test.
 *
 * Register it in your {@see \Testo\Application\ApplicationConfig} `$plugins` list:
 *
 * ```php
 * // testo.php
 * return new ApplicationConfig(
 *     plugins: [new DoublePlugin()],
 *     suites:  [new SuiteConfig(name: 'Unit', location: ['tests/Unit'])],
 * );
 * ```
 *
 * Once registered, `Double::verifyAll()` runs after every test, so unmet `expects()` and
 * `received()` assertions fail the test with no per-test `verify()` teardown boilerplate.
 *
 * @api
 */
final readonly class DoublePlugin implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        $container->get(InterceptorCollector::class)->addInterceptor(new DoubleInterceptor());
    }
}
