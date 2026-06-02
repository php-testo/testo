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
    /**
     * Channel the assertion history is written to (see {@see \Testo\Messenger}).
     */
    public const CHANNEL_HISTORY = 'assert-history';

    #[\Override]
    public function configure(Container $container): void
    {
        $collector = $container->get(InterceptorCollector::class);
        # Registered by class so the container autowires the Messenger into the collector.
        $collector->addInterceptor(AssertCollectorInterceptor::class);
        $collector->addInterceptor(new ExpectationsInterceptor());
    }
}
