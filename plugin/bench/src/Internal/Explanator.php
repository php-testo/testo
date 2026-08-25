<?php

declare(strict_types=1);

namespace Testo\Bench\Internal;

use Testo\Bench\Dto\CaseResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\Line;
use Testo\Bench\Dto\Report;
use Testo\Bench\Dto\ValueRel;

/**
 * @internal
 */
final class Explanator
{
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
        \uasort($results, static fn(CaseResult $a, CaseResult $b): int => $a->favg <=> $b->favg);

        # Calculate relative values
        $baseMean = $results[0]->mean;
        $baseMed = $results[0]->med;
        $baseTime = $results[0]->favg;
        $place = 1;

        $lines = [];
        foreach ($results as $k => $result) {
            $lines[$k] = new Line(
                place: $place++,
                name: $cases[$k]->name,
                avg: new ValueRel(
                    value: $result->mean,
                    diff: $baseMean > 0 ? ($result->mean - $baseMean) / $baseMean * 100 : 0.0,
                ),
                med: new ValueRel(
                    value: $result->med,
                    diff: $baseMed > 0 ? ($result->med - $baseMed) / $baseMed * 100 : 0.0,
                ),
                rstdev: $result->rstdev,
                favg: new ValueRel(
                    value: $result->favg,
                    diff: $baseTime > 0 ? ($result->favg - $baseTime) / $baseTime * 100 : 0.0,
                ),
                frstdev: $result->frstdev,
                rejected: $result->rejected,
                reports: self::explain($result, $cases[$k]),
            );
        }

        \ksort($lines);

        return $lines;
    }

    /**
     * Generates a comment for a given case result, potentially based on the presence of outliers or other factors.
     *
     * @return list<Report> A list of reports (comments, warnings, errors) related to the benchmark execution.
     */
    private static function explain(CaseResult $caseResult, CaseSet $caseSet): array
    {
        $result = [];

        $frstdev = $caseResult->frstdev;
        $mean = $caseResult->favg;
        $outliers = $caseResult->rejected;
        $total = \count($caseSet->iterations);
        $calls = $caseSet->iterations[0]->calls;
        $iterTime = $mean * $calls;
        $outlierRate = $total > 0 && $outliers > 0
            ? ($outliers / $total) * 100
            : 0.0;
        $skew = $caseResult->med > 0
            ? \abs($mean - $caseResult->med) / $caseResult->med * 100
            : 0.0;

        // RStDev* × iter time
        match (true) {
            $frstdev > 20.0 && $iterTime < 10.0 => $result[] = new Report\UnreliableLowIterTime($frstdev, $iterTime),
            $frstdev > 20.0 => $result[] = new Report\VeryHighVariance($frstdev),
            $frstdev >= 10.0 && $iterTime < 10.0 => $result[] = new Report\HighVarianceLowIterTime($frstdev, $iterTime),
            $frstdev >= 10.0 => $result[] = new Report\HighVariance($frstdev),
            default => null,
        };

        // Outliers
        match (true) {
            $outliers >= 3 && $outlierRate > 20.0 => $result[] = new Report\ExtremeOutlierRate($outliers, $outlierRate),
            $outliers >= 3 && $outlierRate >= 10.0 => $result[] = new Report\TooManyOutliers($outliers, $outlierRate),
            default => null,
        };

        // |Mean* − Median| / Median
        match (true) {
            $skew > 10.0 => $result[] = new Report\HeavilySkewed($skew),
            $skew >= 5.0 => $result[] = new Report\SkewedDistribution($skew),
            default => null,
        };

        // Compound conditions
        $outliers >= 3 && $outlierRate > 10.0 && $skew > 5.0
            and $result[] = new Report\BimodalBehavior($outliers, $outlierRate, $skew);

        // Noisy environment × iter time
        match (true) {
            $frstdev > 10.0 && $outliers < 3 && $iterTime < 10.0 => $result[] = new Report\InsufficientIterTime($frstdev, $iterTime),
            $frstdev > 10.0 && $outliers < 3 => $result[] = new Report\NoisyEnvironment($frstdev, $outliers),
            default => null,
        };

        // Preventive: low iter time with acceptable RStDev
        $iterTime < 10.0 && $frstdev <= 10.0
            and $result[] = new Report\LowIterTime($iterTime);

        return $result;
    }
}
