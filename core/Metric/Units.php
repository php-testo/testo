<?php

declare(strict_types=1);

namespace Testo\Metric;

use Testo\Common\Reflection;
use Testo\Metric\Exception\IncompatibleUnits;
use Testo\Metric\Exception\InvalidUnitDeclaration;

/**
 * Reads unit families off their attributes and does the arithmetic between units.
 *
 * Any enum implementing {@see Unit} is a family; its {@see Dimension} and {@see Factor} attributes are
 * reflected once and kept for the rest of the process. A conversion is a plain ratio of factors, so an
 * exact integer result stays an integer (`2` kilobytes are `2048` bytes, not `2048.0`) and only an
 * inexact one becomes a float.
 *
 * @api
 */
final class Units
{
    /** @var array<class-string<Unit>, UnitFamily> */
    private static array $families = [];

    private function __construct() {}

    /**
     * @param Unit|class-string<Unit> $unit
     * @throws InvalidUnitDeclaration
     */
    public static function family(Unit|string $unit): UnitFamily
    {
        $class = \is_string($unit) ? $unit : $unit::class;

        return self::$families[$class] ??= self::reflect($class);
    }

    public static function factor(Unit $unit): int|float
    {
        return self::family($unit)->factor($unit);
    }

    /**
     * @throws IncompatibleUnits When the units belong to different families.
     */
    public static function convert(int|float $value, Unit $from, Unit $to): int|float
    {
        $from::class === $to::class or throw IncompatibleUnits::between($from, $to);
        if ($from === $to) {
            return $value;
        }

        $family = self::family($from);
        return self::ratio($value, $family->factor($from), $family->factor($to));
    }

    /**
     * The same amount in the unit of its family that reads most compactly; see {@see UnitFamily::fit()}.
     *
     * @template TUnit of Unit
     * @param TUnit $unit
     * @return Metric<TUnit>
     */
    public static function compact(int|float $value, Unit $unit): Metric
    {
        $family = self::family($unit);
        /** @var TUnit $fit */
        $fit = $family->fit(self::convert($value, $unit, $family->base()));

        return new Metric(self::convert($value, $unit, $fit), $fit);
    }

    /**
     * `$value * $from / $to`, kept in integer arithmetic while every operand is an integer so an exact
     * result stays an `int`; one float operand makes the whole ratio a float.
     */
    private static function ratio(int|float $value, int|float $from, int|float $to): int|float
    {
        if (\is_int($value) && \is_int($from) && \is_int($to)) {
            return $value * $from / $to;
        }

        return (float) $value * (float) $from / (float) $to;
    }

    /**
     * @param class-string $class
     */
    private static function reflect(string $class): UnitFamily
    {
        \enum_exists($class) && \is_subclass_of($class, Unit::class)
            or throw InvalidUnitDeclaration::notAUnitEnum($class);

        $enum = new \ReflectionEnum($class);
        /** @var non-empty-string $name */
        $name = (Reflection::fetchClassAttributes($enum, attributeClass: Dimension::class, limit: 1)[0] ?? null)
            ?->newInstance()->name
            ?? \strtolower($enum->getShortName());

        /** @var array<non-empty-string, int|float> $factors */
        $factors = [];
        foreach (Reflection::fetchEnumCaseAttributes($enum, Factor::class) as $case => $attributes) {
            $factor = ($attributes[0] ?? null)?->newInstance()->value ?? 1;
            $factor > 0 or throw InvalidUnitDeclaration::nonPositiveFactor($class, $case, $factor);
            $factors[$case] = $factor;
        }
        $factors === [] and throw InvalidUnitDeclaration::notAUnitEnum($class);

        // `asort` is stable, so equal factors keep their declaration order.
        $ordered = $factors;
        \asort($ordered);
        /** @var non-empty-list<Unit> $units */
        $units = \array_map(
            static fn(string $case): Unit => \constant("{$class}::{$case}"),
            \array_keys($ordered),
        );

        /** @var class-string<Unit> $class */
        return new UnitFamily($class, $name, $units, $factors);
    }
}
