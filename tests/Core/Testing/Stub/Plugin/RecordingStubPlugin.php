<?php

declare(strict_types=1);

namespace Tests\Core\Testing\Stub\Plugin;

use Internal\Container\Container;
use Testo\Common\EventListenerCollector;
use Testo\Common\PluginConfigurator;
use Testo\Event\Framework\SessionFinished;
use Testo\Pipeline\InterceptorCollector;

/**
 * Exercises every registration seam a plugin can touch, so {@see \Testo\Testing\Helper\PluginTester} has
 * something to capture: an interceptor added as an instance and one added as a class-string, a listener, a
 * binding and a service registration.
 */
final class RecordingStubPlugin implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        $interceptors = $container->get(InterceptorCollector::class);
        $interceptors->addInterceptor(new StubInterceptor());
        $interceptors->addInterceptor(AnotherStubInterceptor::class);

        $container->get(EventListenerCollector::class)
            ->addListener(SessionFinished::class, static fn(): null => null);

        $container->bind(StubService::class, StubService::class);
        $container->set(new StubService());
    }
}
