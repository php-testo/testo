<?php

declare(strict_types=1);

namespace Testo\Bench\Internal;

use Testo\Bench\Dto\CaseResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\Snap;
use Testo\Inline\TestInline;

/**
 * @internal
 */
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

        # A zero MAD means a degenerately narrow distribution — over half the samples share a value,
        # which a coarse timer produces routinely — not that every sample off the median is an outlier.
        # Filtering on a zero limit would keep only exact-median samples and reject the rest, so skip it.
        $filtered = $mad > 0.0
            ? \array_filter(
                $averages,
                static fn(float $avg): bool => \abs($avg - $median) <= $limit,
            )
            : $averages;

        # Standard deviation of the original averages for the relative standard deviation
        $sd = self::stdev(...$averages);
        $avg = self::avg(...$averages);
        $rstdev = $avg > 0 ? ($sd / $avg) * 100 : 0.0;

        # Standard deviation, average, and relative standard deviation for the filtered iterations
        $fsd = self::stdev(...$filtered);
        $favg = self::avg(...$filtered);
        $frstdev = $favg > 0 ? ($fsd / $favg) * 100 : 0.0;

        return new CaseResult(
            mean: self::avg(...$averages),
            med: $median,
            rstdev: $rstdev,
            rejected: \count($caseSet->iterations) - \count($filtered),
            favg: $favg,
            frstdev: $frstdev,
        );
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
     * Calculates the population standard deviation of the given values (variance over `n`).
     */
    #[TestInline([], result: 0.0)]
    #[TestInline([3.0], result: 0.0)]
    #[TestInline([3.0, 3.0, 3.0, 3.0], result: 0.0)]
    private static function stdev(float ...$values): float
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
