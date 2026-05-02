<?php

declare(strict_types=1);

namespace Testo\Assert\State;

/**
 * Assertion record.
 */
interface Record extends \Stringable
{
    /**
     * Indicates whether the assertion was successful.
     */
    public function isSuccess(): bool;

    /**
     * Returns user-provided message for the assertion.
     *
     */
    public function getContext(): string;

    /**
     * Get the reason why the assertion or expectation failed.
     *
     * Examples:
     *  - none thrown
     *  - thrown type 'InvalidArgumentException' does not match expected type 'RuntimeException'
     *  - leaked objects found
     *  - expected exactly 42, got 43
     *  - key 'username' not found
     *  - 42 is not greater than 100
     */
    public function getFailReason(): string;

    /**
     * Get detailed information about the failure.
     *
     * For example, a diff between expected and actual values.
     */
    public function getFailDetails(): string;
}
