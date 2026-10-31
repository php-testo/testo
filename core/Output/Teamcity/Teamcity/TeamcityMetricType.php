<?php

declare(strict_types=1);

namespace Testo\Output\Teamcity\Teamcity;

use Testo\Core\Metric\Memory;
use Testo\Metric\Unit;
use Testo\Metric\Metric;
use Testo\Core\Metric\Percent;
use Testo\Core\Metric\Scalar;
use Testo\Core\Metric\Time;

/**
 * The number dimensions a TeamCity `testMetadata` message can carry, and the conversion of a Core
 * {@see Metric} onto one of them.
 *
 * TeamCity charts a number by its `type` but knows only these four. A measurement in any richer unit is
 * converted at this boundary — every {@see Time} unit down to milliseconds, its sole time dimension;
 * every {@see Memory} unit down to bytes — so the source keeps its native precision for every other
 * consumer.
 *
 * @link https://www.jetbrains.com/help/teamcity/reporting-test-metadata.html
 * @internal
 */
enum TeamcityMetricType: string
{
    case Number = 'number';
    case Milliseconds = 'ms';
    case Bytes = 'bytes';
    case Percent = 'percent';

    /**
     * The TeamCity type a metric maps to, and its value expressed in that type's unit.
     *
     * @param Metric<Unit> $metric
     * @return array{self, int|float}
     */
    public static function convert(Metric $metric): array
    {
        $unit = $metric->unit;

        return match (true) {
            $unit instanceof Time => [self::Milliseconds, $metric->to(Time::Milliseconds)->value],
            $unit instanceof Memory => [self::Bytes, $metric->to(Memory::Bytes)->value],
            $unit instanceof Percent => [self::Percent, $metric->value],
            $unit instanceof Scalar => [self::Number, $metric->value],
            default => [self::Number, $metric->value],
        };
    }
}
