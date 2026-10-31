<?php

declare(strict_types=1);

namespace Tests\Metric;

use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Codecov\Covers;
use Testo\Metric\Exception\IncompatibleUnits;
use Testo\Core\Metric\Memory;
use Testo\Metric\Metric;
use Testo\Core\Metric\Time;
use Testo\Test;

#[Test]
#[Covers(Metric::class)]
final class MetricTest
{
    public function toReturnsANewMetricInTheRequestedUnit(): void
    {
        $metric = new Metric(1_500, Time::Microseconds);

        $converted = $metric->to(Time::Milliseconds);

        Assert::same($converted->value, 1.5);
        Assert::same($converted->unit, Time::Milliseconds);
        Assert::same($metric->value, 1_500);
    }

    #[ExpectException(IncompatibleUnits::class)]
    public function toAnotherFamilyIsRefused(): void
    {
        (new Metric(1, Time::Seconds))->to(Memory::Bytes);
    }

    public function compactRescalesToTheMostReadableUnit(): void
    {
        Assert::equals((new Metric(2_048, Memory::Bytes))->compact(), new Metric(2, Memory::Kibibytes));
        Assert::equals((new Metric(0.25, Time::Seconds))->compact(), new Metric(250.0, Time::Milliseconds));
    }
}
