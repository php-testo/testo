<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Teamcity;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Metric\Memory;
use Testo\Metric\Metric;
use Testo\Core\Metric\Percent;
use Testo\Core\Metric\Scalar;
use Testo\Core\Metric\Time;
use Testo\Output\Teamcity\Teamcity\TeamcityMetricType;
use Testo\Test;

#[Test]
#[Covers(TeamcityMetricType::class)]
final class TeamcityMetricTypeTest
{
    public function everyTimeUnitConvertsToMilliseconds(): void
    {
        // TeamCity charts time only in `ms`, so each time unit is scaled onto it at this boundary.
        Assert::same(
            TeamcityMetricType::convert(new Metric(2_000.0, Time::Nanoseconds)),
            [TeamcityMetricType::Milliseconds, 2_000.0 / 1_000_000],
        );
        Assert::same(
            TeamcityMetricType::convert(new Metric(5.0, Time::Microseconds)),
            [TeamcityMetricType::Milliseconds, 5.0 / 1_000],
        );
        Assert::same(
            TeamcityMetricType::convert(new Metric(42.0, Time::Milliseconds)),
            [TeamcityMetricType::Milliseconds, 42.0],
        );
        Assert::same(
            TeamcityMetricType::convert(new Metric(1.5, Time::Seconds)),
            [TeamcityMetricType::Milliseconds, 1.5 * 1_000],
        );
    }

    public function everyByteUnitConvertsToBytes(): void
    {
        Assert::same(
            TeamcityMetricType::convert(new Metric(300, Memory::Bytes)),
            [TeamcityMetricType::Bytes, 300],
        );
        Assert::same(
            TeamcityMetricType::convert(new Metric(2, Memory::Kibibytes)),
            [TeamcityMetricType::Bytes, 2048],
        );
        Assert::same(
            TeamcityMetricType::convert(new Metric(3, Memory::Mebibytes)),
            [TeamcityMetricType::Bytes, 3 * 1024 * 1024],
        );
    }

    public function dimensionlessAndPercentPassThrough(): void
    {
        Assert::same(
            TeamcityMetricType::convert(new Metric(97.3, Percent::Percent)),
            [TeamcityMetricType::Percent, 97.3],
        );
        Assert::same(
            TeamcityMetricType::convert(new Metric(20, Scalar::Number)),
            [TeamcityMetricType::Number, 20],
        );
    }
}
