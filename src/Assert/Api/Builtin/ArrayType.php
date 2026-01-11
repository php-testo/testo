<?php

declare(strict_types=1);

namespace Testo\Assert\Api\Builtin;

use Testo\Assert\State\Assertion\AssertionException;

/**
 * Assertion utilities for array-like data type.
 *
 * Applicable to {@see array} and {@see \ArrayAccess} implementations.
 */
interface ArrayType extends IterableType
{
    /**
     * Asserts that the array contains given key.
     *
     * @param int|string ...$keys The keys to check for existence in the array.
     * @throws AssertionException when the assertion fails.
     */
    public function hasKeys(int|string ...$keys): static;

    /**
     * Asserts that the array does not contain given keys.
     *
     * @param int|string ...$keys The keys to check for non-existence in the array.
     * @throws AssertionException when the assertion fails.
     */
    public function doesNotHaveKeys(int|string ...$keys): static;

    /**
     * Asserts that the array is a list.
     *
     * A list is an array with sequential integer keys starting from 0.
     * Equivalent to {@see array_is_list()} in PHP 8.1+.
     *
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function isList(string $message = ''): static;
}
