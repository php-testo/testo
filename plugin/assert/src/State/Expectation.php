<?php

declare(strict_types=1);

namespace Testo\Assert\State;

/**
 * Interface for expectation records.
 *
 * Supposed format for string representation:
 *
 * ```
 *  Expected that <expectation>, but <wentWrong>.
 *  <details>
 * ```
 *
 * Supposed format for successful expectation:
 *
 * ```
 * Passed expectation that <expectation>.
 * ```
 */
interface Expectation extends Record
{
    /**
     * Get the expected condition.
     *
     * Examples:
     *  - test failed with an exception of type 'RuntimeException'
     *  - test failed with an error
     *  - object of type 'User' was not leaked
     *
     * @return non-empty-string
     */
    public function getExpectation(): string;
}
