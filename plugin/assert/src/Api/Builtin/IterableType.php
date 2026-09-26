<?php

declare(strict_types=1);

namespace Testo\Assert\Api\Builtin;

use Testo\Assert\State\Assertion\AssertionException;

/**
 * Assertion utilities for iterables.
 *
 * The {@see iterable} type includes {@see array} and objects implementing {@see \Traversable} interface.
 *
 * @note A {@see \Generator} can be iterated only once. Using this interface on a generator will exhaust it.
 * @note This interface is not intended to be implemented by userland code.
 *       New methods may be added in minor versions without a major version bump.
 */
interface IterableType
{
    /**
     * Asserts that the iterable is not empty.
     *
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function notEmpty(string $message = ''): static;

    /**
     * Asserts that the iterable contains the given needle.
     *
     * @param mixed $needle The value to look for within the iterable.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function contains(mixed $needle, string $message = ''): static;

    /**
     * Asserts that the iterable does not contain the given needle.
     *
     * @param mixed $needle The value that must be absent from the iterable.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function notContains(mixed $needle, string $message = ''): static;

    /**
     * Asserts that the iterable has the same number of elements as the expected iterable.
     *
     * @param iterable $expected The iterable to compare size against.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function sameSizeAs(iterable $expected, string $message = ''): static;

    /**
     * Asserts that the iterable has the expected number of elements.
     *
     * @param int $expected The expected count of elements.
     * @throws AssertionException when the assertion fails.
     */
    public function hasCount(int $expected): static;

    /**
     * Asserts that all values in the iterable are of the specified type.
     *
     * @param non-empty-string $type The expected type name (e.g., 'int', 'string', 'object', class name)
     *        considered valid by {@see \get_debug_type()}.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function allOf(string $type, string $message = ''): static;

    /**
     * Asserts that every element in the iterable is an instance of the given class or interface.
     *
     * Unlike {@see allOf()}, which matches the exact type, a subclass or an implementation passes.
     * An empty iterable passes.
     *
     * @param class-string $class Fully-qualified class or interface name.
     * @param string $message Optional message for the assertion.
     * @throws \InvalidArgumentException when the class or interface does not exist, before any
     *         element is checked, so an empty iterable throws too.
     * @throws AssertionException when the assertion fails.
     */
    public function allInstanceOf(string $class, string $message = ''): static;

    /**
     * Asserts that every element in the iterable satisfies the given callback predicate.
     *
     * @param callable(mixed): bool $callback A predicate function that receives each element and returns true if it satisfies the condition.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function every(callable $callback, string $message = ''): static;
}
