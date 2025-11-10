<?php

declare(strict_types=1);

namespace Testo\Config;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Testo\Common\Container;
use Testo\Common\Internal\EventDispatcher;

/**
 * Testo default services configuration.
 */
class DefaultServicesConfig implements PluginConfigurator
{
    public readonly array $services;

    /**
     * @param array<class-string, \Closure|string|array|null> $definitions Service definitions.
     *        Bindings for the Container {@see \Testo\Common\Container::bind}.
     *        You can override the default services by providing your own implementations here with the same keys.
     */
    public function __construct(
        array $definitions = [],
    ) {
        $this->services = $definitions + self::getDefaults();
    }

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
        ];
    }
}
