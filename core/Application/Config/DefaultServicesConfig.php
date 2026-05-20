<?php

declare(strict_types=1);

namespace Testo\Application\Config;

use Internal\Container\Container;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Testo\Application\Internal\EventDispatcher;
use Testo\Common\EventListenerCollector;
use Testo\Common\PluginConfigurator;
use Testo\Pipeline\InterceptorCollector;
use Testo\Pipeline\InterceptorProvider;

/**
 * Testo default services configuration.
 *
 * @api
 */
final readonly class DefaultServicesConfig implements PluginConfigurator
{
    /**
     * @var array<class-string, null|class-string|array<string, mixed>|\Closure(Container): object>
     */
    public array $services;

    /**
     * @param array<class-string, null|class-string|array<string, mixed>|\Closure(Container): object> $definitions
     *        Bindings (service definitions) for the Container {@see Container::bind()}.
     *        You can override the default services by providing your own implementations here with the same keys.
     */
    public function __construct(
        array $definitions = [],
    ) {
        $this->services = $definitions + self::getDefaults();
    }

    #[\Override]
    public function configure(Container $container): void
    {
        foreach ($this->services as $key => $definition) {
            $container->bind($key, $definition);
        }
    }

    private static function getDefaults(): array
    {
        return [
            # Event Dispatcher
            EventDispatcherInterface::class => EventDispatcher::class,
            ListenerProviderInterface::class => EventDispatcher::class,
            EventListenerCollector::class => EventDispatcher::class,
            InterceptorCollector::class => InterceptorProvider::class,
        ];
    }
}
