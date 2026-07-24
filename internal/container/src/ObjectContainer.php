<?php

declare(strict_types=1);

namespace Internal\Container;

use Internal\Container\Interanl\State;
use Internal\Fiber\FiberLocal;

/**
 * Simple dependency injection container.
 *
 * Provides service creation and caching with autowiring capabilities.
 * Automatically loads configuration for config classes.
 *
 * The active {@see State} lives in a {@see FiberLocal} so a scope entered inside a fiber stays isolated to
 * that fiber: when several fibers interleave on one event loop, each reads its own scoped bindings across
 * every switch, with no swapping at the suspension boundary.
 *
 * @internal
 * @psalm-internal Testo\Application
 */
final class ObjectContainer implements Container
{
    /** @var FiberLocal<State> */
    private FiberLocal $current;

    public function __construct()
    {
        $this->current = new FiberLocal(new State($this));
    }

    public function addInflector(Inflector $inflector): void
    {
        $this->current->get()->addInflector($inflector);
    }

    #[\Override]
    public function get(string $id, array $arguments = []): object
    {
        return $this->current->get()->get($id, $arguments);
    }

    #[\Override]
    public function has(string $id): bool
    {
        return $this->current->get()->has($id);
    }

    #[\Override]
    public function set(object $service, ?string $id = null, bool $destroy = false): void
    {
        \assert($id === null || $service instanceof $id, "Service must be instance of {$id}.");
        $this->current->get()->set($service, $id, $destroy);
    }

    #[\Override]
    public function make(string $class, array $arguments = []): object
    {
        return $this->current->get()->make($class, $arguments);
    }

    #[\Override]
    public function bind(string $id, \Closure|string|array|null $binding = null): void
    {
        $this->current->get()->bind($id, $binding);
    }

    #[\Override]
    public function scope(\Closure $scope): mixed
    {
        // Clone the active state into a child scope; FiberLocal binds it to the current fiber only and
        // restores the parent on exit — even across a real event-loop suspension inside $scope, where the
        // loop resumes this exact fiber directly. The child state is destroyed after the parent is restored.
        $new = $this->current->get()->clone($this);
        return $this->current->scope($new, fn(): mixed => $scope($this), $new->destroy(...));
    }

    #[\Override]
    public function destroy(): void
    {
        $this->current->get()->destroy();
        unset($this->current);
    }

    public function __clone(): void
    {
        $this->current = new FiberLocal(clone $this->current->get());
    }
}
