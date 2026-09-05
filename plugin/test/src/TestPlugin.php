<?php

declare(strict_types=1);

namespace Testo\Test;

use Internal\Container\Container;
use Testo\Common\PluginConfigurator;
use Testo\Pipeline\InterceptorCollector;
use Testo\Test;
use Testo\Test\Internal\SkipInterceptor;
use Testo\Test\Internal\TestoAttributesLocatorInterceptor;

/**
 * Find tests by the {@see Test} attribute.
 *
 * @api
 */
final readonly class TestPlugin implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        $collector = $container->get(InterceptorCollector::class);
        $collector->addInterceptor(new TestoAttributesLocatorInterceptor());
        $collector->addInterceptor(SkipInterceptor::class);
    }
}
