<?php

declare(strict_types=1);

namespace Testo\Testing;

use Internal\Container\Container;
use Testo\Common\PluginConfigurator;
use Testo\Pipeline\InterceptorCollector;
use Testo\Testing\Attribute\Inject;
use Testo\Testing\Internal\ExpectInterceptor;
use Testo\Testing\Internal\InjectInterceptor;

/**
 * Enables property autowiring for test case classes.
 *
 * Registers {@see InjectInterceptor}, which populates properties marked with
 * {@see Inject} from the DI container right after a test case object is created.
 *
 * @internal
 * @psalm-internal Testo
 */
final readonly class InjectPlugin implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        # Registered as a class-string so the collector resolves it through the
        # container and autowires the {@see Container} dependency.
        $container->get(InterceptorCollector::class)->addInterceptor(InjectInterceptor::class);
        $container->get(InterceptorCollector::class)->addInterceptor(new ExpectInterceptor());
    }
}
