<?php

declare(strict_types=1);

namespace Internal\Container;

use Internal\Container\Interanl\State;

/**
 * Simple dependency injection container.
 *
 * Provides service creation and caching with autowiring capabilities.
 * Automatically loads configuration for config classes.
 *
 * @internal
 * @psalm-internal Testo\Application
 */
final class ObjectContainer implements Container
{
    private State $state;

    public function __construct()
    {
        $this->state = new State($this);
    }

    public function addInflector(Inflector $inflector): void
    {
        $this->state->addInflector($inflector);
    }

    #[\Override]
    public function get(string $id, array $arguments = []): object
    {
        return $this->state->get($id, $arguments);
    }

    #[\Override]
    public function has(string $id): bool
    {
        return $this->state->has($id);
    }

    #[\Override]
    public function set(object $service, ?string $id = null, bool $destroy = false): void
    {
        \assert($id === null || $service instanceof $id, "Service must be instance of {$id}.");
        $this->state->set($service, $id, $destroy);
    }

    #[\Override]
    public function make(string $class, array $arguments = []): object
    {
        return $this->state->make($class, $arguments);
    }

    #[\Override]
    public function bind(string $id, \Closure|string|array|null $binding = null): void
    {
        $this->state->bind($id, $binding);
    }

    #[\Override]
    public function scope(\Closure $scope): mixed
    {
        $oldState = $this->state;
        $newState = $oldState->clone($this);
        try {
            $this->state = $newState;
            if (\Fiber::getCurrent() === null) {
                return $scope($this);
            }

            // Wrap scope into fiber
            $fiber = new \Fiber(static fn(ObjectContainer $c) => $scope($c));
            $value = $fiber->start($this);
            while (!$fiber->isTerminated()) {
                $this->state = $oldState;
                try {
                    $resume = \Fiber::suspend($value);
                } catch (\Throwable $e) {
                    $this->state = $newState;
                    $value = $fiber->throw($e);
                    continue;
                }

                $this->state = $newState;
                $value = $fiber->resume($resume);
            }

            return $fiber->getReturn();
        } finally {
            $this->state = $oldState;
            $newState->destroy();
        }
    }

    #[\Override]
    public function destroy(): void
    {
        $this->state->destroy();
        unset($this->state);
    }

    public function __clone(): void
    {
        $this->state = clone $this->state;
    }
}
