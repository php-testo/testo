<?php

declare(strict_types=1);

namespace Testo\Common\Internal;

use Internal\Destroy\Destroyable;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Testo\Config\EventListenerCollector;

final class EventDispatcher implements
    EventListenerCollector,
    ListenerProviderInterface,
    EventDispatcherInterface,
    Destroyable
{
    /**
     * @var array<class-string, array<int, list<callable>>>
     */
    private array $listeners = [];

    public function addListener(string $eventName, callable $callback, int $priority = 0): void
    {
        $this->listeners[$eventName][$priority][] = $callback;
        \krsort($this->listeners[$eventName], \SORT_NUMERIC);
    }

    /**
     * @template T
     * @param T $event
     * @return iterable<callable(T): mixed>
     */
    public function getListenersForEvent(object $event): iterable
    {
        $eventName = $event::class;

        # Cache hierarchy per event class
        static $hierarchy = [];
        $hierarchy[$eventName] ??= [
            $eventName,
            ...\array_values(\class_parents($event)),
            ...\array_values(\class_implements($event)),
        ];

        foreach ($hierarchy[$eventName] as $class) {
            foreach ($this->listeners[$class] ?? [] as $priorityGroup) {
                foreach ($priorityGroup as $listener) {
                    yield $listener;
                }
            }
        }
    }

    /**
     * @template T
     * @param T $event
     * @return T
     */
    public function dispatch(object $event): object
    {
        /** @var callable $listener */
        foreach ($this->getListenersForEvent($event) as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                return $event;
            }

            # Set to a new variable to prevent changing of the variable via reference in listener
            $arg = $event;
            $listener($arg);
        }

        return $event;
    }

    public function destroy(): void
    {
        $this->listeners = [];
    }
}
