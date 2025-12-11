<?php

declare(strict_types=1);

namespace Testo\Assert\Api\Builtin;

use Testo\Assert\State\AssertException;

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
     * @param int|string $key The key to check for in the array.
     * @param string $message Optional message for the assertion.
     * @throws AssertException when the assertion fails.
     *
     */
    public function hasKey(int|string $key, string $message = ''): self;

    /**
     * Asserts that the array is a list.
     *
     * A list is an array with sequential integer keys starting from 0.
     * Equivalent to {@see array_is_list()} in PHP 8.1+.
     *
     * @param string $message Optional message for the assertion.
     * @throws AssertException when the assertion fails.
     *
     */
    public function isList(string $message = ''): self;
}
