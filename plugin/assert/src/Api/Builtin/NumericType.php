<?php

declare(strict_types=1);

namespace Testo\Assert\Api\Builtin;

use Testo\Assert\State\Assertion\AssertionException;

/**
 * Assertion utilities for numeric data types.
 *
 * Numeric data types include integers, floats, and numeric strings.
 *
 * @note This interface is not intended to be implemented by userland code.
 *       New methods may be added in minor versions without a major version bump.
 */
interface NumericType
{
    /**
     * Asserts that numeric value is greater than the given minimum.
     *
     * @param int|float $min Minimum threshold to compare with.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function greaterThan(int|float $min, string $message = ''): static;

    /**
     * Asserts that the numeric value is greater than or equal to given minimum.
     *
     * @param int|float $min Minimum threshold to compare with.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function greaterThanOrEqual(int|float $min, string $message = ''): static;

    /**
     * Asserts that numeric value is less than the given maximum.
     *
     * @param int|float $max Maximum threshold to compare with.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function lessThan(int|float $max, string $message = ''): static;

    /**
     * Asserts that the numeric value is less than or equal to given maximum.
     *
     * @param int|float $max Maximum threshold to compare with.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function lessThanOrEqual(int|float $max, string $message = ''): static;

    /**
     * Asserts that the numeric value is between the given minimum and maximum (inclusive).
     *
     * @param int|float $min Minimum threshold (inclusive).
     * @param int|float $max Maximum threshold (inclusive).
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function between(int|float $min, int|float $max, string $message = ''): static;
}
