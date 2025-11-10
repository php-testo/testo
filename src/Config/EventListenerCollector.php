<?php

declare(strict_types=1);

namespace Testo\Config;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Provides API to configure {@see EventDispatcherInterface}.
 *
 * The interface is accessible only on configuration phase.
 */
interface EventListenerCollector
{
    /**
     * Adds event listener.
     *
     * @template T
     * @param class-string<T> $eventName
     * @param callable(T): mixed $callback
     */
    public function addListener(string $eventName, callable $callback, int $priority = 0): void;
}
