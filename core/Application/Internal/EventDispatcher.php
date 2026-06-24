<?php

declare(strict_types=1);

namespace Testo\Application\Internal;

use Internal\Destroy\Destroyable;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Testo\Common\ErrorReporter;
use Testo\Common\EventListenerCollector;
use Testo\Common\Messenger;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Event\Message\MessageReceived;

/**
 * A simple event dispatcher that supports listener priorities and event propagation control.
 *
 * @internal
 * @psalm-internal Testo\Application
 */
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

    /**
     * Re-entrancy guard: true while we are dispatching a {@see MessageReceived} report about a failed
     * listener, so a listener that throws *on that report* falls back to the real stderr stream instead
     * of recursing forever.
     */
    private bool $reportingError = false;

    #[\Override]
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
    #[\Override]
    public function getListenersForEvent(object $event): iterable
    {
        $eventName = $event::class;

        # Cache hierarchy per event class
        static $hierarchy = [];
        $parents = \class_parents($event);
        $interfaces = \class_implements($event);
        $hierarchy[$eventName] ??= [
            $eventName,
            ...($parents === false ? [] : \array_values($parents)),
            ...($interfaces === false ? [] : \array_values($interfaces)),
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
     * @template T of object
     * @param T $event
     * @return T
     */
    #[\Override]
    public function dispatch(object $event): object
    {
        /** @var callable $listener */
        foreach ($this->getListenersForEvent($event) as $listener) {
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                return $event;
            }

            # Set to a new variable to prevent changing of the variable via reference in listener
            $arg = $event;

            try {
                $listener($arg);
            } catch (\Throwable $e) {
                # A faulty listener must not abort the dispatch chain: report it on the stderr channel
                # and carry on with the remaining listeners.
                $this->reportListenerError($e);
            }
        }

        return $event;
    }

    #[\Override]
    public function destroy(): void
    {
        $this->listeners = [];
    }

    /**
     * Surface a listener failure on the {@see \Testo\Common\Messenger::CHANNEL_STDERR} channel without recursing.
     */
    private function reportListenerError(\Throwable $e): void
    {
        # Already reporting (a MessageReceived listener itself threw) — last resort, avoid an infinite
        # loop: a concise one-liner straight to the real stderr stream, skipping the channel system.
        if ($this->reportingError) {
            return;
        }

        $this->reportingError = true;
        try {
            $this->dispatch(new MessageReceived(new Message(
                \microtime(true),
                Messenger::CHANNEL_STDERR,
                Level::Error,
                ErrorReporter::format($e),
            )));
        } finally {
            $this->reportingError = false;
        }
    }
}
