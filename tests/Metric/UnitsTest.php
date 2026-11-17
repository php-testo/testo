<?php

declare(strict_types=1);

namespace Tests\Metric;

use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Codecov\Covers;
use Testo\Metric\Exception\IncompatibleUnits;
use Testo\Metric\Exception\InvalidUnitDeclaration;
use Testo\Core\Metric\Memory;
use Testo\Metric\UnitFamily;
use Testo\Metric\Units;
use Testo\Metric\Metric;
use Testo\Core\Metric\Time;
use Testo\Data\DataSet;
use Testo\Test;
use Tests\Metric\Fixture\Distance;
use Tests\Metric\Fixture\NegativeFactor;
use Tests\Metric\Fixture\NotAUnit;
use Tests\Metric\Fixture\Plain;

#[Test]
#[Covers(Units::class)]
#[Covers(UnitFamily::class)]
#[Covers(IncompatibleUnits::class)]
#[Covers(InvalidUnitDeclaration::class)]
final class UnitsTest
{
    public function familyReadsDimensionAndFactorsFromAttributes(): void
    {
        $family = Units::family(Distance::class);

        Assert::same($family->class, Distance::class);
        Assert::same($family->name, 'distance');
        Assert::same($family->factors, ['Kilometers' => 1_000, 'Centimeters' => 0.01, 'Meters' => 1]);
    }

    public function unitsAreOrderedByFactorRegardlessOfDeclaration(): void
    {
        $family = Units::family(Distance::class);

        Assert::same($family->units, [Distance::Centimeters, Distance::Meters, Distance::Kilometers]);
        Assert::same($family->base(), Distance::Centimeters);
    }

    public function familyWithoutAttributesFallsBackToClassNameAndUnitFactors(): void
    {
        $family = Units::family(Plain::class);

        Assert::same($family->name, 'plain');
        Assert::same($family->factors, ['One' => 1, 'Other' => 1]);
        // Equal factors keep declaration order, so the first declared case is the base.
        Assert::same($family->base(), Plain::One);
    }

    public function familyIsReflectedOnceAndReused(): void
    {
        $first = Units::family(Time::class);

        Assert::same(Units::family(Time::class), $first);
        Assert::same(Units::family(Time::Seconds), $first);
    }

    public function forgottenFamilyIsReflectedAgain(): void
    {
        $before = Units::family(Plain::class);
        (new \ReflectionProperty(Units::class, 'families'))->setValue(null, []);

        Assert::notSame(Units::family(Plain::class), $before);
    }

    #[DataSet([Distance::Centimeters, 0.01])]
    #[DataSet([Distance::Meters, 1])]
    #[DataSet([Distance::Kilometers, 1_000])]
    public function factorOfUnit(Distance $unit, int|float $expected): void
    {
        Assert::same(Units::factor($unit), $expected);
        Assert::same($unit->factor(), $expected);
    }

    public function convertKeepsExactIntegerResultsIntegral(): void
    {
        Assert::same(Units::convert(2, Memory::Kibibytes, Memory::Bytes), 2048);
        Assert::same(Units::convert(3, Time::Seconds, Time::Microseconds), 3_000_000);
        Assert::same(Units::convert(2048, Memory::Bytes, Memory::Kibibytes), 2);
    }

    public function convertTurnsInexactResultsIntoFloats(): void
    {
        Assert::same(Units::convert(1_500, Time::Microseconds, Time::Milliseconds), 1.5);
        Assert::same(Units::convert(1, Memory::Bytes, Memory::Kibibytes), 1 / 1024);
    }

    public function convertHandlesFractionalFactors(): void
    {
        Assert::same(Units::convert(250, Distance::Centimeters, Distance::Meters), 2.5);
        Assert::same(Units::convert(2, Distance::Kilometers, Distance::Centimeters), 200_000.0);
    }

    public function convertToTheSameUnitReturnsTheValueUntouched(): void
    {
        Assert::same(Units::convert(42, Time::Seconds, Time::Seconds), 42);
        Assert::same(Units::convert(4.2, Time::Seconds, Time::Seconds), 4.2);
    }

    public function convertIsAvailableOnTheUnitItself(): void
    {
        Assert::same(Time::Milliseconds->convert(1.5, Time::Microseconds), 1_500.0);
    }

    #[ExpectException(IncompatibleUnits::class)]
    public function convertAcrossFamiliesIsRefused(): void
    {
        Units::convert(1, Time::Seconds, Memory::Bytes);
    }

    #[DataSet([1_500, Time::Microseconds, new Metric(1.5, Time::Milliseconds)], 'scales up to the next unit')]
    #[DataSet([2.5, Time::Seconds, new Metric(2.5, Time::Seconds)], 'already compact')]
    #[DataSet([999, Time::Nanoseconds, new Metric(999, Time::Nanoseconds)], 'below the next unit stays')]
    #[DataSet([1_000, Time::Nanoseconds, new Metric(1, Time::Microseconds)], 'exactly one of the next unit moves')]
    #[DataSet([0.5, Time::Microseconds, new Metric(500.0, Time::Nanoseconds)], 'below one of the current unit scales down')]
    #[DataSet([0, Time::Seconds, new Metric(0, Time::Nanoseconds)], 'zero lands in the base')]
    #[DataSet([-3_000_000, Time::Microseconds, new Metric(-3, Time::Seconds)], 'sign is ignored when fitting')]
    #[DataSet([3 * 1024 ** 3, Memory::Bytes, new Metric(3, Memory::Gibibytes)], 'memory scales by 1024')]
    #[DataSet([1, Plain::Other, new Metric(1, Plain::Other)], 'equal factors keep the given unit')]
    public function compactPicksTheLargestUnitTheValueReaches(int|float $value, $unit, Metric $expected): void
    {
        Assert::equals(Units::compact($value, $unit), $expected);
    }

    #[ExpectException(InvalidUnitDeclaration::class)]
    public function nonPositiveFactorIsRejected(): void
    {
        Units::family(NegativeFactor::class);
    }

    #[ExpectException(InvalidUnitDeclaration::class)]
    public function enumOutsideTheUnitContractIsRejected(): void
    {
        /** @phpstan-ignore-next-line */
        Units::family(NotAUnit::class);
    }

    #[ExpectException(InvalidUnitDeclaration::class)]
    public function nonEnumClassIsRejected(): void
    {
        /** @phpstan-ignore-next-line */
        Units::family(\stdClass::class);
    }
}
