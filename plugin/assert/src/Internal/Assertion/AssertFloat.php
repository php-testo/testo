<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Assertion;

use Testo\Assert\Api\Builtin\FloatType;
use Testo\Assert\Internal\Assertion\Traits\NumericTrait;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\State\Assertion\AssertionComposite;
use Testo\Assert\State\Assertion\AssertionException;

/**
 * Assertion utilities for integer data type.
 *
 * @internal
 * @psalm-internal Testo\Assert
 */
final readonly class AssertFloat implements FloatType
{
    use NumericTrait;

    public function __construct(
        private float $value,
        private AssertionComposite $parent,
    ) {}

    /**
     * Validate that the given value is a float and return an AssertFloat instance.
     *
     * @param mixed $value The value to be asserted as float.
     * @return self An instance of AssertFloat.
     * @throws AssertionException when the value is not a float.
     */
    public static function validateAndCreate(mixed $value): self
    {
        \is_float($value) or StaticState::typeFail('float', $value);

        $parent = StaticState::typeSuccess('float', $value);
        return new self($value, $parent);
    }
}
