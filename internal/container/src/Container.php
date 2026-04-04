<?php

declare(strict_types=1);

namespace Internal\Container;

use Internal\Destroy\Destroyable;
use Psr\Container\ContainerInterface;

/**
 * Application dependency injection container.
 *
 * Manages service instances throughout the application lifecycle. Services are lazy-loaded
 * and cached for reuse. The container handles dependencies between services and
 * provides a way to customize service instantiation through bindings.
 *
 * ```
 *  // Retrieving a service instance
 *  $downloader = $container->get(Downloader::class);
 *
 *  // Binding a factory for service creation
 *  $container->bind(Logger::class, function (Container $c) {
 *      return new Logger($c->get(OutputInterface::class));
 *  });
 * ```
 */
interface Container extends Destroyable, ContainerInterface
{
    /**
     * Retrieves a service from the container.
     *
     * If the service is requested for the first time, it will be instantiated and persisted for future requests.
     *
     * @template T
     * @param class-string<T> $id Service identifier
     * @param array<string, mixed> $arguments Constructor arguments used only on first instantiation
     * @return T The requested service instance
     *
     * @psalm-suppress MoreSpecificImplementedParamType, InvalidReturnType
     */
    #[\Override]
    public function get(string $id, array $arguments = []): object;

    /**
     * Checks if the service is registered in the container.
     *
     * It means that the container has a cached service instance or a binding.
     *
     * @param class-string $id Service identifier
     * @return bool Whether the service is available
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    #[\Override]
    public function has(string $id): bool;

    /**
     * Registers an existing service instance in the container.
     *
     * @template T
     * @param T $service Service instance to register
     * @param class-string<T>|null $id Optional service identifier (defaults to object's class)
     * @param bool $destroy Whether the container should manage the service's lifecycle and call its destroy
     *        method on Container destruction (if it implements {@see Destroyable}).
     */
    public function set(object $service, ?string $id = null, bool $destroy = false): void;

    /**
     * Creates a new instance without storing it in the container.
     *
     * @template T
     * @param class-string<T> $class Class to instantiate
     * @param array<string, mixed> $arguments Constructor arguments
     * @return T Newly created instance
     */
    public function make(string $class, array $arguments = []): object;

    /**
     * Declares a factory or predefined arguments for the specified class.
     *
     * Configures how a service should be instantiated.
     *
     * @template T
     * @param class-string<T> $id Service identifier
     * @param null|class-string<T>|array<string, mixed>|\Closure(Container): T $binding Factory
     *        function, constructor arguments, or alias class name.
     */
    public function bind(string $id, \Closure|string|array|null $binding = null): void;

    /**
     * Invokes given closure or function withing specific container scope.
     *
     * @template T
     * @param \Closure(Container): T $scope
     * @return T
     * @throws \Throwable
     */
    public function scope(\Closure $scope): mixed;
}
