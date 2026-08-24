<?php

declare(strict_types=1);

namespace Testo\Output\Rendering;

use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\Line;
use Testo\Bench\Dto\Report;
use Testo\Bench\Dto\Report\Severity;
use Testo\Bench\Dto\Snap;

/**
 * Benchmark measurements as data rather than as the ASCII table the terminal prints.
 *
 * A benchmark's structured result is the test's return value, so it needs no cooperation from the bench
 * plugin beyond being installed — `testo/bench` stays a soft dependency, and a project without it never
 * loads any of this.
 *
 * The numbers are taken from {@see Line}, the ranked view the plugin computes: it carries the relative
 * difference against the baseline case and the diagnostics that explain when a measurement should not be
 * trusted. Times stay in microseconds, the unit the plugin measures in — converting here would only add a
 * rounding step for the renderer to undo.
 *
 * Two shapes are offered, because the reporters need different ones: {@see map()} for documents that can
 * hold structure (HTML, JSON), {@see metrics()} for protocols whose vocabulary is a flat list of named
 * numbers (JUnit `<property>`, TeamCity `testMetadata`).
 *
 * @internal
 */
final class BenchMapper
{
    private function __construct() {}

    /**
     * True when the value is a benchmark result this mapper understands.
     *
     * @phpstan-assert-if-true BenchResult $value
     */
    public static function supports(mixed $value): bool
    {
        return \class_exists(BenchResult::class) && $value instanceof BenchResult;
    }

    /**
     * @return array<non-empty-string, mixed>
     */
    public static function map(BenchResult $result): array
    {
        $iterations = $result->cases === [] ? 0 : \count($result->cases[0]->iterations);

        $cases = [];
        $diagnostics = [];
        /** @var Line $line */
        foreach ($result->lines as $line) {
            $set = self::caseSet($result, $line->name);
            $cases[] = self::case($line, self::callsOf($set), self::memoryOf($set));

            /** @var Report $report */
            foreach ($line->reports as $report) {
                $diagnostics[] = [
                    'case' => $line->name,
                    'kind' => self::shortName($report::class),
                    'severity' => self::severity($report->severity),
                    'reason' => $report->reason,
                    'advice' => $report->advice,
                ];
            }
        }

        return [
            'iterations' => $iterations,
            'cases' => $cases,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * The same numbers flattened into `bench.<case>.<metric>` entries, for protocols that carry metrics
     * as a flat list of named numbers rather than as a document.
     *
     * Unit is part of the key (`meanUs`, `rstdevPct`, `memoryBytes`) because a flat namespace has nowhere
     * else to record it, and a consumer plotting these has no other way to label an axis.
     *
     * @return array<non-empty-string, int|float>
     */
    public static function metrics(BenchResult $result): array
    {
        $metrics = [
            'bench.iterations' => $result->cases === [] ? 0 : \count($result->cases[0]->iterations),
        ];

        /** @var Line $line */
        foreach ($result->lines as $line) {
            $set = self::caseSet($result, $line->name);
            $prefix = "bench.{$line->name}.";

            $metrics[$prefix . 'calls'] = self::callsOf($set);
            $metrics[$prefix . 'place'] = $line->place;
            $metrics[$prefix . 'meanUs'] = $line->avg->value;
            $metrics[$prefix . 'meanDiffPct'] = $line->avg->diff;
            $metrics[$prefix . 'medianUs'] = $line->med->value;
            $metrics[$prefix . 'medianDiffPct'] = $line->med->diff;
            $metrics[$prefix . 'rstdevPct'] = $line->rstdev;
            $metrics[$prefix . 'filteredMeanUs'] = $line->favg->value;
            $metrics[$prefix . 'filteredMeanDiffPct'] = $line->favg->diff;
            $metrics[$prefix . 'filteredRstdevPct'] = $line->frstdev;
            $metrics[$prefix . 'rejected'] = $line->rejected;
            $metrics[$prefix . 'memoryBytes'] = self::memoryOf($set);
        }

        /** @var array<non-empty-string, int|float> */
        return $metrics;
    }

    /**
     * One {@see metrics()} value as text.
     *
     * An integer keeps its exact form; a float is written in fixed notation with trailing zeros
     * trimmed, so a near-zero difference reads as `0.000001` rather than as `1.0E-6` — which a JUnit
     * consumer or a TeamCity `type='number'` metric would have to special-case.
     */
    public static function formatMetric(int|float $value): string
    {
        return \is_int($value)
            ? (string) $value
            : \rtrim(\rtrim(\sprintf('%.6F', $value), '0'), '.');
    }

    /**
     * @param int<0, max> $calls
     * @param int<0, max> $memory
     * @return array<non-empty-string, mixed>
     */
    private static function case(Line $line, int $calls, int $memory): array
    {
        return [
            'name' => $line->name,
            'place' => $line->place,
            'calls' => $calls,
            # Microseconds, as the bench plugin measures.
            'mean' => $line->avg->value,
            'median' => $line->med->value,
            # Percent against the baseline case: 0.0 for the baseline, positive for slower.
            'meanDiff' => $line->avg->diff,
            'medianDiff' => $line->med->diff,
            # Standard deviation as a percentage of the mean.
            'rstdev' => $line->rstdev,
            # The same pair with outliers dropped, which is what a noisy environment leaves worth reading.
            'filteredMean' => $line->favg->value,
            'filteredMeanDiff' => $line->favg->diff,
            'filteredRstdev' => $line->frstdev,
            'rejected' => $line->rejected,
            # Bytes allocated per iteration.
            'memory' => $memory,
        ];
    }

    /**
     * The measurement set behind a ranked line, matched by case name.
     */
    private static function caseSet(BenchResult $result, string $name): ?CaseSet
    {
        /** @var CaseSet $case */
        foreach ($result->cases as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return int<0, max>
     */
    private static function callsOf(?CaseSet $case): int
    {
        /** @var list<Snap> $snaps */
        $snaps = $case?->iterations ?? [];

        return $snaps === [] ? 0 : \max(0, $snaps[0]->calls);
    }

    /**
     * Largest per-iteration figure rather than an average: iterations of one case measure the same work,
     * so they differ only by noise, and the largest is the one that had to fit in memory.
     *
     * @return int<0, max>
     */
    private static function memoryOf(?CaseSet $case): int
    {
        $peak = 0;
        /** @var Snap $snap */
        foreach ($case?->iterations ?? [] as $snap) {
            $snap->memory > $peak and $peak = $snap->memory;
        }

        return \max(0, $peak);
    }

    /**
     * @return non-empty-string
     */
    private static function severity(Severity $severity): string
    {
        return \strtolower($severity->name);
    }

    /**
     * Class name without its namespace — `HighVariance` rather than the FQN, since the namespace says
     * nothing a reader of the report needs.
     *
     * @param class-string $class
     * @return non-empty-string
     */
    private static function shortName(string $class): string
    {
        $position = \strrpos($class, '\\');
        /** @var non-empty-string */
        return $position === false ? $class : \substr($class, $position + 1);
    }
}
