<?php

declare(strict_types=1);

namespace Testo\Testing\Internal\Collector;

use Testo\Common\EventListenerCollector;

/**
 * An {@see EventListenerCollector} that records the listeners a plugin registers instead of dispatching to
 * them. Each entry keeps the event class, the callback and the priority the plugin passed.
 *
 * @internal
 * @psalm-internal Testo\Testing
 */
final class RecordingEventListenerCollector implements EventListenerCollector
{
    /** @var list<array{event: class-string, callback: callable, priority: int}> */
    public array $listeners = [];

    #[\Override]
    public function addListener(string $eventName, callable $callback, int $priority = 0): void
    {
        $this->listeners[] = ['event' => $eventName, 'callback' => $callback, 'priority' => $priority];
    }
}
