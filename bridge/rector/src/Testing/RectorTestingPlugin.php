<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Testing;

use Internal\Container\Container;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Bridge\Rector\Testing\Internal\Middleware\RectorFixtureFinder;
use Testo\Bridge\Rector\Testing\Internal\Middleware\RectorFixtureInterceptor;
use Testo\Common\PluginConfigurator;
use Testo\Pipeline\InterceptorCollector;

/**
 * Suite plugin enabling "inline tests for Rector rules": it discovers rules carrying
 * {@see TestRectorFixtures} ({@see RectorFixtureFinder}) and runs each rule's fixtures as data sets
 * ({@see RectorFixtureInterceptor}).
 *
 * Attach it to a suite whose finder points at the rule sources (see this package's `suites.php`).
 *
 * @api
 */
final readonly class RectorTestingPlugin implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        $collector = $container->get(InterceptorCollector::class);

        $collector->addInterceptor(new RectorFixtureFinder());
        $collector->addInterceptor(new RectorFixtureInterceptor($container->get(EventDispatcherInterface::class)));
    }
}
