<?php

declare(strict_types=1);

namespace Testo\Test\Factory;

use Testo\Test\TestCaseFactory;

/**
 * Basic factory using PHP reflection for object creation.
 *
 * Example:
 * ```php
 * $factory = new ReflectionFactory();
 *
 * // Simple class with no dependencies
 * $test = $factory->create(new \ReflectionClass(SimpleTest::class));
 *
 * // Class with constructor parameters
 * $test = $factory->create(
 *     new \ReflectionClass(ParameterizedTest::class),
 *     ['param1', 42]
 * );
 * ```
 *
 * @template T of object
 * @implements TestCaseFactory<T>
 */
final class ReflectionFactory implements TestCaseFactory
{
    /**
     * Creates an instance using reflection's newInstance method.
     *
     * @param \ReflectionClass<T> $class Reflection of the class to instantiate
     * @param array<array-key, mixed> $args Constructor arguments in the order expected by the constructor
     *
     * @return T New instance of the class
     *
     * @throws \ReflectionException If the class cannot be instantiated:
     *         - Class is abstract or an interface
     *         - Constructor is not accessible
     *         - Required constructor parameters are missing
     *         - Constructor throws an exception
     */
    #[\Override]
    public function create(\ReflectionClass $class, array $args = []): object
    {
        if ($class->hasMethod('__construct') && $class->getMethod('__construct')->isPublic()) {
            return $class->newInstanceArgs($args);
        }

        return $class->newInstanceWithoutConstructor();
    }
}
