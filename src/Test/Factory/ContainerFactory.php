<?php

declare(strict_types=1);

namespace Testo\Test\Factory;

use Psr\Container\ContainerInterface;
use Testo\Test\TestCaseFactory;

/**
 * Factory that delegates object creation to a PSR-11 dependency injection container.
 *
 * Example:
 * ```php
 * // Setup container with test dependencies
 * $container = new Container();
 * $container->set(DatabaseConnection::class, $db);
 * $container->set(UserRepository::class, fn() => new UserRepository($db));
 *
 * $factory = new ContainerFactory($container);
 *
 * // Test class constructor receives injected dependencies
 * class UserTest {
 *     public function __construct(
 *         private UserRepository $users,
 *         private DatabaseConnection $db
 *     ) {}
 * }
 *
 * $test = $factory->create(new \ReflectionClass(UserTest::class));
 * // UserTest is created with injected UserRepository and DatabaseConnection
 * ```
 *
 * @template T of object
 * @implements TestCaseFactory<T>
 */
final class ContainerFactory implements TestCaseFactory
{
    /**
     * @param ContainerInterface $container PSR-11 container for resolving dependencies
     */
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * @param \ReflectionClass<T> $class
     * @param array<array-key, mixed> $args
     *
     * @return T
     *
     * @throws \Psr\Container\NotFoundExceptionInterface If the class is not found in the container
     * @throws \Psr\Container\ContainerExceptionInterface If the container encounters an error during instantiation
     */
    #[\Override]
    public function create(\ReflectionClass $class, array $args = []): object
    {
        return $this->container->get($class->getName());
    }
}
