<?php

declare(strict_types=1);

namespace Testo\Bridge\Mockery;

use Internal\Container\Container;
use Testo\Bridge\Mockery\Internal\MockeryInterceptor;
use Testo\Common\PluginConfigurator;
use Testo\Pipeline\InterceptorCollector;

/**
 * Plugin that automatically closes the Mockery container after every test.
 *
 * Register it in your {@see \Testo\Application\ApplicationConfig} `$plugins` list:
 *
 * ```php
 * // testo.php
 * return new ApplicationConfig(
 *     plugins: [new MockeryPlugin()],
 *     suites:  [new SuiteConfig(name: 'Unit', location: ['tests/Unit'])],
 * );
 * ```
 *
 * Once registered, `\Mockery::close()` is called automatically in a `finally`
 * block after each test, so you never need to add teardown boilerplate and mock
 * expectations are always verified.
 *
 * @api
 */
final readonly class MockeryPlugin implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        $container->get(InterceptorCollector::class)->addInterceptor(new MockeryInterceptor());
    }
}
