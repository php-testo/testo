<?php

declare(strict_types=1);

namespace Testo\Bench\Internal;

use Testo\Bench\Dto\CaseResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\Line;
use Testo\Bench\Dto\Snap;
use Testo\Bench\Dto\ValueRel;
use Testo\Inline\TestInline;

final class Calculator
{
    /**
     * Calculates the median, average, and relative standard deviation of the given case set.
     *
     * The method uses the Median Absolute Deviation (MAD) to identify and filter out outliers
     * before calculating the final statistics. The relative standard deviation is expressed as a percentage.
     */
    public static function calculate(CaseSet $caseSet): CaseResult
    {
        # Avg before filtering outliers
        $averages = \array_map(
            static fn(Snap $snap): float => $snap->time / $snap->calls,
            $caseSet->iterations,
        );
        $median = self::med(...$averages);

        # Calc abs deviation from median for each iteration
        $deviations = \array_map(static fn(float $avg): float => \abs($avg - $median), $averages);

        # Calc median of the deviations
        $mad = self::med(...$deviations);

        # Set limit for outliers
        $limit = 3 * $mad * 1.4826;

        # Filter out iterations that are considered outliers
        $filtered = \array_filter(
            $averages,
            static fn(float $avg): bool => \abs($avg - $median) <= $limit,
        );

        # Calc RMS, average, and relative standard deviation for the filtered iterations
        $rms = self::rms(...$filtered);
        $avg = self::avg(...$filtered);
        $rstdev = $rms / $avg * 100;

        return new CaseResult(
            mean: self::avg(...$averages),
            med: $median,
            rejected: \count($caseSet->iterations) - \count($filtered),
            avg: $avg,
            rstdev: $rstdev,
        );
    }

    /**
     * Prepares the lines for the final report based on the calculated results.
     *
     * @param list<CaseSet> $cases The list of cases that were benchmarked.
     * @param list<CaseResult> $results The corresponding results for each case.
     * @return list<Line> The prepared lines for the report, with placeholders for names and places.
     */
    public static function prepareLines(array $cases, array $results): array
    {
        # Sort by time ascending
        \uasort($results, static fn(CaseResult $a, CaseResult $b): int => $a->avg <=> $b->avg);

        # Calculate relative values
        $baseMean = $results[0]->mean;
        $baseMed = $results[0]->med;
        $baseTime = $results[0]->avg;
        $place = 1;

        $lines = [];
        foreach ($results as $k => $result) {
            $lines[$k] = new Line(
                place: $place++,
                name: $cases[$k]->name,
                mean: new ValueRel(
                    value: $result->mean,
                    diff: $baseTime > 0 ? ($result->mean - $baseMean) / $baseMean * 100 : 0.0,
                ),
                med: new ValueRel(
                    value: $result->med,
                    diff: $baseMed > 0 ? ($result->med - $baseMed) / $baseMed * 100 : 0.0,
                ),
                avg: new ValueRel(
                    value: $result->avg,
                    diff: $baseTime > 0 ? ($result->avg - $baseTime) / $baseTime * 100 : 0.0,
                ),
                rstdev: $result->rstdev,
                rejected: $result->rejected,
            );
        }

        \ksort($lines);

        return $lines;
    }

    /**
     * Calculates the median of the given values.
     */
    #[TestInline([1.0], result: 1.0)]
    #[TestInline([-1.0, 1.0], result: 0.0)]
    #[TestInline([2.0, 1.0, 10.0], result: 2.0)]
    #[TestInline([1.0, 2.0, 3.0, 4.0], result: 2.5)]
    private static function med(float ...$values): float
    {
        \sort($values);
        $count = \count($values);
        $mid = (int) \floor($count / 2);

        return $count % 2 === 0
            ? ($values[$mid - 1] + $values[$mid]) / 2
            : $values[$mid];
    }

    /**
     * Calculates the root mean square (RMS) of the given values.
     */
    #[TestInline([], result: 0.0)]
    #[TestInline([3.0], result: 3.0)]
    private static function rms(float ...$values): float
    {
        $n = \count($values);
        if ($n === 0) {
            return 0.0;
        }

        $avg = self::avg(...$values);
        $sumOfSquares = \array_reduce(
            $values,
            static fn(float $carry, float $value): float => $carry + ($value - $avg) ** 2,
            0.0,
        );

        return \sqrt($sumOfSquares / $n);
    }

    /**
     * Calculates the average of the given values.
     */
    #[TestInline([], result: 0.0)]
    #[TestInline([1.0, 2.0, 3.0], result: 2.0)]
    private static function avg(float ...$values): float
    {
        $n = \count($values);
        if ($n === 0) {
            return 0.0;
        }

        $sum = \array_sum($values);
        return $sum / $n;
    }
}
