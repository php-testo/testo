<?php

declare(strict_types=1);

namespace Internal\Container\Interanl;

use Internal\Container\Container;
use Internal\Container\Factoriable;
use Internal\Container\Inflector;
use Internal\Container\ObjectContainer;
use Internal\Destroy\Destroyable;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Yiisoft\Injector\Injector;

/**
 * @internal
 */
final class State
{
    /** @var array<class-string, object> */
    private array $cache = [];

    /**
     * Bindings:
     * Array of:
     * - {@see \Closure}: just a factory
     * - {@see array}: arguments for the Injector
     * with the class name as key.
     *
     * @var array<class-string, array|\Closure(self): object>
     */
    private array $factory = [];

    /** @var list<Inflector> */
    private array $inflectors = [];

    private Injector $injector;

    /** @var array<int, Destroyable> */
    private array $destroy = [];
    private ObjectContainer $container;

    /**
     * @psalm-suppress PropertyTypeCoercion
     */
    public function __construct(ObjectContainer $container)
    {
        $this->container = $container;
        $this->injector = (new Injector($container))->withCacheReflections(false);
        $this->cache[Injector::class] = $this->injector;
        $this->cache[Container::class] = $container;
        $this->cache[self::class] = $container;
        $this->cache[ObjectContainer::class] = $container;
        $this->cache[ContainerInterface::class] = $container;
    }

    public function addInflector(Inflector $inflector): void
    {
        $this->inflectors[] = $inflector;
    }

    /**
     * @template T
     * @param class-string<T> $id
     * @param array<string, mixed> $arguments
     * @return T
     */
    public function get(string $id, array $arguments = []): object
    {
        $result = $this->cache[$id] ?? null;

        if ($result === null) {
            $this->cache[$id] = $result = $this->make($id, $arguments);
            $result instanceof Destroyable and $this->destroy[\spl_object_id($result)] = $result;
        }

        return $result;
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->cache) || \array_key_exists($id, $this->factory);
    }

    public function set(object $service, ?string $id = null, bool $destroy = false): void
    {
        $this->cache[$id ?? $service::class] = $service;
        $service instanceof Destroyable and $this->destroy[\spl_object_id($service)] = $service;
    }

    /**
     * @template T
     * @param class-string<T> $class
     * @param array<string, mixed> $arguments
     * @return T
     */
    public function make(string $class, array $arguments = []): object
    {
        $binding = $this->factory[$class] ?? [];

        if (\is_array($binding)) {
            try {
                $result = $this->injector->make($class, \array_merge($binding, $arguments));
            } catch (\Throwable $e) {
                throw new class("Unable to create object of class $class.", previous: $e) extends \RuntimeException implements NotFoundExceptionInterface {};
            }
        } else {
            $result = $binding($this);
        }

        \assert($result instanceof $class, "Created object must be instance of {$class}.");

        foreach ($this->inflectors as $inflector) {
            $result = $inflector->inflect($result, $this->container);
        }

        return $result;
    }

    /**
     * @template T
     * @param class-string<T> $id
     * @param null|class-string<T>|array<string, mixed>|\Closure(mixed ...): T $binding
     */
    public function bind(string $id, \Closure|string|array|null $binding = null): void
    {
        $binding ??= $id;

        if (\is_string($binding)) {
            \class_exists($binding) or throw new \InvalidArgumentException(
                "Class `$binding` does not exist.",
            );
            $id === $binding or \is_a($binding, $id, true) or throw new \InvalidArgumentException(
                "Alias for `$id` must be instance of `$id`, `$binding` given.",
            );

            /** @var class-string<T> $binding */
            $binding = match (true) {
                $id !== $binding => static fn(self $self): object => $self->get($binding),
                // \is_a($binding, Factoriable::class, true) => static fn(self $self): object => $binding::create(...),
                \is_a($binding, Factoriable::class, true) => static fn(self $self): object => $self
                    ->injector->invoke($binding::create(...)),
                default => static fn(self $self): object => $self->injector->make($binding),
            };
        } elseif ($binding instanceof \Closure) {
            $binding = static fn(self $self): object => $self->injector->invoke($binding);
        } elseif ($binding === null) {
            $binding = [];
        }

        $this->factory[$id] = $binding;
    }

    public function destroy(): void
    {
        unset($this->cache, $this->factory, $this->injector, $this->container);
        while ($inflector = \array_pop($this->inflectors)) {
            $inflector instanceof Destroyable and $inflector->destroy();
        }

        while ($service = \array_pop($this->destroy)) {
            $service->destroy();
        }
    }

    public function clone(ObjectContainer $container): self
    {
        $self = clone $this;
        [$cache, $self->cache, $self->destroy] = [$self->cache, [], []];
        $self->__construct($container);

        /** @var array<int, object> $cloned */
        $cloned = [];

        foreach ($cache as $id => $service) {
            if (\array_key_exists($id, $self->cache)) {
                continue;
            }

            $reflection = new \ReflectionClass($service);
            if ($reflection->isReadOnly() || $reflection->isEnum()) {
                $self->cache[$id] = $service;
                continue;
            }

            $oid = \spl_object_id($service);
            $c = $cloned[$oid] ?? null;
            if ($c === null) {
                $c = clone $service;
                $cloned[$oid] = $c;
                $reflection->implementsInterface(Destroyable::class) and $self->destroy[\spl_object_id($c)] = $c;
            }

            $self->cache[$id] = $c;
        }

        return $self;
    }
}
