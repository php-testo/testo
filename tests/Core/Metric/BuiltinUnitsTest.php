<?php

declare(strict_types=1);

namespace Tests\Core\Metric;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Metric\Memory;
use Testo\Core\Metric\Percent;
use Testo\Core\Metric\Scalar;
use Testo\Core\Metric\Time;
use Testo\Metric\UnitConversion;
use Testo\Metric\Units;
use Testo\Metric\Unit;
use Testo\Data\DataSet;
use Testo\Test;

#[Test]
#[Covers(Time::class)]
#[Covers(Memory::class)]
#[Covers(Percent::class)]
#[Covers(Scalar::class)]
#[Covers(UnitConversion::class)]
final class BuiltinUnitsTest
{
    #[DataSet([Time::Nanoseconds, 1])]
    #[DataSet([Time::Microseconds, 1_000])]
    #[DataSet([Time::Milliseconds, 1_000_000])]
    #[DataSet([Time::Seconds, 1_000_000_000])]
    #[DataSet([Memory::Bytes, 1])]
    #[DataSet([Memory::Kibibytes, 1024])]
    #[DataSet([Memory::Mebibytes, 1_048_576])]
    #[DataSet([Memory::Gibibytes, 1_073_741_824])]
    #[DataSet([Percent::Percent, 1])]
    #[DataSet([Scalar::Number, 1])]
    public function factorsMatchTheUnitScale(Unit $unit, int $factor): void
    {
        Assert::same($unit->factor(), $factor);
    }

    #[DataSet([Time::class, 'time', Time::Nanoseconds])]
    #[DataSet([Memory::class, 'memory', Memory::Bytes])]
    #[DataSet([Percent::class, 'percent', Percent::Percent])]
    #[DataSet([Scalar::class, 'number', Scalar::Number])]
    public function familiesAreNamedAndBased(string $class, string $name, Unit $base): void
    {
        $family = Units::family($class);

        Assert::same($family->name, $name);
        Assert::same($family->base(), $base);
    }
}
