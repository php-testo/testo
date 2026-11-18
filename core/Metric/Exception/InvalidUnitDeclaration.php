<?php

declare(strict_types=1);

namespace Testo\Metric\Exception;

use Testo\Metric\Unit;

/**
 * Thrown when a {@see Unit} enum is declared in a way the metric core cannot read — a non-enum class,
 * a class that does not implement the interface, a non-positive factor, or no case with factor `1`.
 *
 * @api
 */
final class InvalidUnitDeclaration extends \LogicException
{
    public static function notAUnitEnum(string $class): self
    {
        return new self(\sprintf('`%s` must be an enum implementing `%s`.', $class, Unit::class));
    }

    /**
     * @param class-string $class
     * @param non-empty-string $case
     */
    public static function nonPositiveFactor(string $class, string $case, int|float $factor): self
    {
        return new self(\sprintf('Factor of `%s::%s` must be positive, got `%s`.', $class, $case, (string) $factor));
    }

    /**
     * @param class-string $class
     */
    public static function missingBase(string $class): self
    {
        return new self(\sprintf('`%s` must have a case with factor `1` to express the other factors in.', $class));
    }
}
