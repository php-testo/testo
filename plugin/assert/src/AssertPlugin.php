<?php

declare(strict_types=1);

namespace Testo\Assert;

use Internal\Container\Container;
use Testo\Assert;
use Testo\Assert\Internal\Middleware\AssertCollectorInterceptor;
use Testo\Assert\Internal\Middleware\ExpectationsInterceptor;
use Testo\Common\PluginConfigurator;
use Testo\Expect;
use Testo\Pipeline\InterceptorCollector;

/**
 * Helps with collecting assertions and expectations for tests.
 *
 * @see Assert
 * @see Expect
 *
 * @api
 */
final readonly class AssertPlugin implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        $collector = $container->get(InterceptorCollector::class);
        $collector->addInterceptor(new AssertCollectorInterceptor());
        $collector->addInterceptor(new ExpectationsInterceptor());
    }
}
