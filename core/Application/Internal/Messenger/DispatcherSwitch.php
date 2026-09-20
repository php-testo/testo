<?php

declare(strict_types=1);

namespace Testo\Application\Internal\Messenger;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The dispatcher {@see \Testo\Application\Internal\MessengerHub} announces its messages on, re-pointable
 * at the dispatcher of the container scope that is currently open.
 *
 * The hub is scope-shared and built in the root scope, so the dispatcher it was constructed with is the
 * root one; a container scope gets a clone of its parent's dispatcher, and only that clone knows the
 * listeners registered inside the scope. Re-pointing on scope entry is what makes a message written by a
 * test reach the renderers and plugins of the scope it was written in.
 *
 * TODO rethink: this is a manual switch driven by {@see \Testo\Application\Application}. The container could
 *      announce scope entry/exit itself, so this and any other scope-shared service holding a scoped
 *      dependency follow the scope without the application having to know about them.
 *
 * @internal
 */
final class DispatcherSwitch implements EventDispatcherInterface
{
    public function __construct(
        private EventDispatcherInterface $target,
    ) {}

    /**
     * Points at `$target` and returns the dispatcher pointed at before, for the caller to restore on scope exit.
     */
    public function switch(EventDispatcherInterface $target): EventDispatcherInterface
    {
        [$previous, $this->target] = [$this->target, $target];
        return $previous;
    }

    #[\Override]
    public function dispatch(object $event): object
    {
        return $this->target->dispatch($event);
    }
}
