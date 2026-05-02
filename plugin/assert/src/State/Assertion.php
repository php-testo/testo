<?php

declare(strict_types=1);

namespace Testo\Assert\State;

/**
 * Interface for assertion records.
 *
 * Expected format for failed assertions:
 *
 * ```
 *  Failed that <value> <assertion>: <wentWrong>.
 *  <details>
 * ```
 *
 * Expected format for successful assertions:
 * ```
 *  Assert that <value> <assertion>.
 * ```
 */
interface Assertion extends Record
{
    /**
     * Get the value that was asserted.
     *
     * @return non-empty-string
     */
    public function getValue(): string;

    /**
     * Get the assertion that was performed.
     *
     * Examples:
     *  - is greater than 10
     *  - contains key 'username'
     *  - has count of 5
     *  - is instance of `DateTimeInterface`
     *
     * @return non-empty-string
     */
    public function getAssertion(): string;
}
