<?php

declare(strict_types=1);

namespace Testo\Test\Factory;

use Testo\Test\TestCaseFactory;

/**
 * Factory that uses a custom callable for object creation.
 *
 * Provides maximum flexibility by delegating creation to a user-defined closure.
 * This allows implementing custom creation logic such as:
 * - Object pooling or caching
 * - Conditional instantiation based on class attributes
 * - Mock/stub injection for specific tests
 * - Integration with custom DI systems
 * - Lazy initialization patterns
 * - Special constructor logic or factory methods
 *
 * The callable receives both the class reflection and constructor arguments,
 * giving full control over the instantiation process.
 *
 * Example - Simple factory:
 * ```php
 * $factory = new CallableFactory(
 *     fn(\ReflectionClass $class, array $args) => $class->newInstanceArgs($args)
 * );
 * ```
 *
 * Example - Conditional mock injection:
 * ```php
 * $factory = new CallableFactory(
 *     function(\ReflectionClass $class, array $args) {
 *         if ($class->implementsInterface(RequiresDatabaseInterface::class)) {
 *             return new $class->name($mockDatabase, ...$args);
 *         }
 *         return $class->newInstanceArgs($args);
 *     }
 * );
 * ```
 *
 * Example - Object pooling:
 * ```php
 * $pool = [];
 * $factory = new CallableFactory(
 *     function(\ReflectionClass $class, array $args) use (&$pool) {
 *         $key = $class->getName();
 *         return $pool[$key] ??= $class->newInstanceArgs($args);
 *     }
 * );
 * ```
 *
 * Example - Integration with custom factory:
 * ```php
 * $customFactory = new TestCaseFactory();
 * $factory = new CallableFactory(
 *     fn(\ReflectionClass $class) => $customFactory->create($class->getName())
 * );
 * ```
 *
 * @template T of object
 * @implements TestCaseFactory<T>
 */
final class CallableFactory implements TestCaseFactory
{
    /**
     * @param \Closure(\ReflectionClass<T>, array<array-key, mixed>): T $callable
     */
    public function __construct(
        private readonly \Closure $callable,
    ) {}

    #[\Override]
    public function create(\ReflectionClass $class, array $args = []): object
    {
        return ($this->callable)($class, $args);
    }
}
