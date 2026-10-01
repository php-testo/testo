<?php

declare(strict_types=1);

namespace Testo\Lifecycle;

use Internal\Container\Container;
use Testo\Common\PluginConfigurator;
use Testo\Lifecycle\Internal\LifecycleInterceptor;
use Testo\Pipeline\InterceptorCollector;

/**
 * Plugin that enables lifecycle attributed methods.
 *
 * Lifecycle methods are methods that are executed before or after tests.
 * They are defined by the following attributes:
 * - {@see BeforeClass}
 * - {@see BeforeTest}
 * - {@see AfterTest}
 * - {@see AfterClass}
 *
 * @api
 */
final readonly class LifecyclePlugin implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        $container->get(InterceptorCollector::class)->addInterceptor(LifecycleInterceptor::class);
    }
}
