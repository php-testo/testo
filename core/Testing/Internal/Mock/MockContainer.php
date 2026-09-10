<?php

declare(strict_types=1);

namespace Testo\Testing\Internal\Mock;

use Internal\Container\Container;
use Internal\Container\ObjectContainer;

/**
 * A {@see Container} that records every {@see self::set()} and {@see self::bind()} a plugin makes so a test
 * can assert on the bindings it declared. Resolution is delegated to a real backing container, so a plugin
 * that reads services back through {@see self::get()} still works.
 *
 * Use {@see self::preset()} to seed a service the plugin will resolve (a recording collector, a stub input)
 * without it counting as one of the plugin's own registrations.
 *
 * @internal
 * @psalm-internal Testo\Testing
 */
final class MockContainer implements Container
{
    /** @var list<array{service: object, id: class-string, destroy: bool}> */
    public array $registrations = [];

    /** @var list<array{id: class-string, binding: \Closure|class-string|array<string, mixed>|null}> */
    public array $bindings = [];

    private readonly Container $inner;

    public function __construct(?Container $inner = null)
    {
        $this->inner = $inner ?? new ObjectContainer();
    }

    /**
     * Seed a service the plugin will resolve, bypassing the recording so it is not mistaken for one of the
     * plugin's own registrations.
     *
     * @param class-string|null $id Defaults to the service's own class.
     */
    public function preset(object $service, ?string $id = null): void
    {
        $this->inner->set($service, $id);
    }

    #[\Override]
    public function get(string $id, array $arguments = []): object
    {
        return $this->inner->get($id, $arguments);
    }

    #[\Override]
    public function has(string $id): bool
    {
        return $this->inner->has($id);
    }

    #[\Override]
    public function set(object $service, ?string $id = null, bool $destroy = false): void
    {
        $this->registrations[] = ['service' => $service, 'id' => $id ?? $service::class, 'destroy' => $destroy];
        $this->inner->set($service, $id, $destroy);
    }

    #[\Override]
    public function make(string $class, array $arguments = []): object
    {
        return $this->inner->make($class, $arguments);
    }

    #[\Override]
    public function bind(string $id, \Closure|string|array|null $binding = null): void
    {
        $this->bindings[] = ['id' => $id, 'binding' => $binding];
        $this->inner->bind($id, $binding);
    }

    #[\Override]
    public function scope(\Closure $scope): mixed
    {
        return $this->inner->scope($scope);
    }

    #[\Override]
    public function destroy(): void
    {
        $this->inner->destroy();
    }
}
