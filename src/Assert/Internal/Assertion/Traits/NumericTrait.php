<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Assertion\Traits;

use Testo\Assert\State\AssertException;
use Testo\Assert\State\Assertion\AssertionComposite;
use Testo\Attribute\AssertMethod;

/**
 * Contains methods for comparing numeric values
 * @property int|float $value
 * @property AssertionComposite $parent
 */
trait NumericTrait
{
    /**
     * Asserts that numeric value is greater than the given minimum.
     *
     * @param int|float $min Minimum threshold to compare with.
     * @param string $message Optional message for the assertion.
     * @throws AssertException when the assertion fails.
     */
    #[AssertMethod]
    #[\Override]
    public function greaterThan(int|float $min, string $message = ''): static
    {
        $str = "greater than `{$min}`";
        if ($this->value > $min) {
            $this->parent->success($str, $message);
            return $this;
        }

        throw $this->parent->fail(
            assertion: $str,
            reason: "the value is not greater than {$min}",
            context: $message,
        );
    }

    /**
     * Asserts that the numeric value is greater than or equal to given minimum.
     *
     * @param int|float $min Minimum threshold to compare with.
     * @param string $message Optional message for the assertion.
     * @throws AssertException when the assertion fails.
     */
    #[AssertMethod]
    #[\Override]
    public function greaterThanOrEqual(int|float $min, string $message = ''): static
    {
        $str = "greater than or equal to `{$min}`";
        if ($this->value >= $min) {
            $this->parent->success($str, $message);
            return $this;
        }

        throw $this->parent->fail(
            assertion: $str,
            reason: "the value is not greater than or equal to {$min}",
            context: $message,
        );
    }

    /**
     * Asserts that numeric value is less than the given maximum.
     *
     * @param int|float $max Maximum threshold to compare with.
     * @param string $message Optional message for the assertion.
     * @throws AssertException when the assertion fails.
     */
    #[AssertMethod]
    #[\Override]
    public function lessThan(int|float $max, string $message = ''): static
    {
        $str = "less than `{$max}`";
        if ($this->value < $max) {
            $this->parent->success($str, $message);
            return $this;
        }

        throw $this->parent->fail(
            assertion: $str,
            reason: "the value is not less than {$max}",
            context: $message,
        );
    }

    /**
     * Asserts that the numeric value is less than or equal to given maximum.
     *
     * @param int|float $max Maximum threshold to compare with.
     * @param string $message Optional message for the assertion.
     * @throws AssertException when the assertion fails.
     */
    #[AssertMethod]
    #[\Override]
    public function lessThanOrEqual(int|float $max, string $message = ''): static
    {
        $str = "less than or equal to `{$max}`";
        if ($this->value <= $max) {
            $this->parent->success($str, $message);
            return $this;
        }

        throw $this->parent->fail(
            assertion: $str,
            reason: "the value is not less than or equal to {$max}",
            context: $message,
        );
    }
}
