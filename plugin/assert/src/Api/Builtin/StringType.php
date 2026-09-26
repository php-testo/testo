<?php

declare(strict_types=1);

namespace Testo\Assert\Api\Builtin;

use Testo\Assert\State\Assertion\AssertionException;

/**
 * Assertion utilities for string data type.
 *
 * @note This interface is not intended to be implemented by userland code.
 *       New methods may be added in minor versions without a major version bump.
 */
interface StringType
{
    /**
     * Asserts that the string contains the given substring.
     *
     * @param string $needle Substring to search for.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function contains(string $needle, string $message = ''): static;

    /**
     * Asserts that the string does not contain the given substring.
     *
     * @param string $needle Substring to search for.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function notContains(string $needle, string $message = ''): static;

    /**
     * Asserts that the string starts with the given prefix.
     *
     * @param string $prefix Expected beginning of the string.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function startsWith(string $prefix, string $message = ''): static;

    /**
     * Asserts that the string ends with the given suffix.
     *
     * @param string $suffix Expected end of the string.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function endsWith(string $suffix, string $message = ''): static;
}
