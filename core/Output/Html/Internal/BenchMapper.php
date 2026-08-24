<?php

declare(strict_types=1);

namespace Testo\Output\Html\Internal;

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
 * difference against the fastest case, which is the comparison a reader actually makes, and the
 * diagnostics that explain when a measurement should not be trusted. Times stay in microseconds, the
 * unit the plugin measures in — converting here would only add a rounding step for the renderer to undo.
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
        $callsByCase = [];
        /** @var CaseSet $case */
        foreach ($result->cases as $case) {
            /** @var list<Snap> $snaps */
            $snaps = $case->iterations;
            $callsByCase[$case->name] = $snaps === [] ? 0 : $snaps[0]->calls;
        }

        $cases = [];
        $diagnostics = [];
        /** @var Line $line */
        foreach ($result->lines as $line) {
            $cases[] = self::case($line, $callsByCase[$line->name] ?? 0);

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
     * @param int<0, max> $calls
     * @return array<non-empty-string, mixed>
     */
    private static function case(Line $line, int $calls): array
    {
        return [
            'name' => $line->name,
            'place' => $line->place,
            'calls' => $calls,
            # Microseconds, as the bench plugin measures.
            'mean' => $line->avg->value,
            'median' => $line->med->value,
            # Percent against the fastest case: 0.0 for the baseline, positive for slower.
            'meanDiff' => $line->avg->diff,
            # Standard deviation as a percentage of the mean.
            'rstdev' => $line->rstdev,
            # The same pair with outliers dropped, which is what a noisy environment leaves worth reading.
            'filteredMean' => $line->favg->value,
            'filteredRstdev' => $line->frstdev,
            'rejected' => $line->rejected,
        ];
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
