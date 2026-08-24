<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Assertion;

use Testo\Assert\Api\Builtin\NumericType;
use Testo\Assert\Internal\Assertion\Traits\NumericTrait;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\State\Assertion\AssertionComposite;
use Testo\Assert\State\Assertion\AssertionException;

/**
 * Assertion utilities for numeric data type.
 *
 * Numeric covers integers, floats and numeric strings; a numeric string is normalised to
 * `int|float` (via `+ 0`) up front so every {@see NumericTrait} comparison runs against a
 * real number instead of relying on PHP's string-to-number coercion at each call.
 *
 * @internal
 * @psalm-internal Testo\Assert
 */
final readonly class AssertNumeric implements NumericType
{
    use NumericTrait;

    public function __construct(
        public int|float $value,
        private AssertionComposite $parent,
    ) {}

    /**
     * Validate that the given value is numeric (int, float, or numeric string) and return an
     * AssertNumeric instance.
     *
     * @param mixed $value The value to be asserted as numeric.
     *
     * @throws AssertionException when the value is not numeric.
     */
    public static function validateAndCreate(mixed $value): self
    {
        \is_int($value) || \is_float($value) || (\is_string($value) && \is_numeric($value))
            or StaticState::typeFail('numeric', $value);

        $parent = StaticState::typeSuccess('numeric', $value);
        return new self(\is_string($value) ? $value + 0 : $value, $parent);
    }
}
