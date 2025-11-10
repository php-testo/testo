<?php

declare(strict_types=1);

namespace Testo\Common\Internal;

use Internal\Destroy\Destroyable;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Testo\Common\Container;
use Testo\Common\Factoriable;
use Testo\Common\Inflector;
use Yiisoft\Injector\Injector;

/**
 * Simple dependency injection container.
 *
 * Provides service creation and caching with autowiring capabilities.
 * Automatically loads configuration for config classes.
 *
 * @internal
 */
final class ObjectContainer implements Container
{
    /** @var array<class-string, object> */
    private array $cache = [];

    /** @var array<class-string, array|\Closure(mixed ...): object> */
    private array $factory = [];

    /** @var list<Inflector> */
    private array $inflectors = [];

    private readonly Injector $injector;

    /**
     * @psalm-suppress PropertyTypeCoercion
     */
    public function __construct()
    {
        $this->injector = (new Injector($this))->withCacheReflections(false);
        $this->cache[Injector::class] = $this->injector;
        $this->cache[Container::class] = $this;
        $this->cache[self::class] = $this;
        $this->cache[ObjectContainer::class] = $this;
        $this->cache[ContainerInterface::class] = $this;
    }

    public function addInflector(Inflector $inflector): void
    {
        $this->inflectors[] = $inflector;
    }

    public function get(string $id, array $arguments = []): object
    {
        /** @psalm-suppress InvalidReturnStatement */
        return $this->cache[$id] ??= $this->make($id, $arguments);
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->cache) || \array_key_exists($id, $this->factory);
    }

    public function set(object $service, ?string $id = null): void
    {
        \assert($id === null || $service instanceof $id, "Service must be instance of {$id}.");
        $this->cache[$id ?? $service::class] = $service;
    }

    public function make(string $class, array $arguments = []): object
    {
        $binding = $this->factory[$class] ?? null;

        if ($binding instanceof \Closure) {
            $result = $this->injector->invoke($binding);
        } else {
            try {
                $result = $this->injector->make($class, \array_merge((array) $binding, $arguments));
            } catch (\Throwable $e) {
                throw new class("Unable to create object of class $class.", previous: $e) extends \RuntimeException implements NotFoundExceptionInterface {};
            }
        }

        \assert($result instanceof $class, "Created object must be instance of {$class}.");

        foreach ($this->inflectors as $inflector) {
            $result = $inflector->inflect($result, $this);
        }

        return $result;
    }

    /**
     * @template T
     * @param class-string<T> $id Service identifier
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
                $id !== $binding => fn(): object => $this->get($binding),
                \is_a($binding, Factoriable::class, true) => $binding::create(...),
                default => fn(): object => $this->injector->make($binding),
            };
        }

        $this->factory[$id] = $binding;
    }

    public function destroy(): void
    {
        unset($this->cache, $this->factory, $this->injector);
        while ($inflector = \array_pop($this->inflectors)) {
            $inflector instanceof Destroyable and $inflector->destroy();
        }
    }
}
