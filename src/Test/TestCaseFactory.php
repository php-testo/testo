<?php

declare(strict_types=1);

namespace Testo\Test;

/**
 * Factory interface for creating test case class instances.
 *
 * Factories provide a flexible way to create test case instances, supporting
 * different creation strategies such as reflection-based creation, dependency
 * injection containers, or custom factory functions.
 *
 * Common use cases:
 * - Simple creation via reflection ({@see Factory\ReflectionFactory})
 * - DI container integration ({@see Factory\ContainerFactory})
 * - Custom factory logic ({@see Factory\CallableFactory})
 *
 * Example usage:
 * ```php
 * $factory = new ReflectionFactory();
 * $testCase = $factory->create(new \ReflectionClass(MyTest::class));
 * ```
 *
 * @template T of object
 */
interface TestCaseFactory
{
    /**
     * Creates an instance of the specified test case class.
     *
     * @param \ReflectionClass<T> $class Reflection of the class to create
     * @param array<array-key, mixed> $args Optional constructor arguments (implementation-specific)
     *
     * @return T Instance of the requested class
     */
    public function create(\ReflectionClass $class, array $args = []): object;
}
