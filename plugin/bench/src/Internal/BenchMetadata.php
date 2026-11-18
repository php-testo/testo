<?php

declare(strict_types=1);

namespace Testo\Bench\Internal;

use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\Line;
use Testo\Bench\Dto\Snap;
use Testo\Core\Metadata\MetadataEntry;
use Testo\Core\Metric\Memory;
use Testo\Core\Metric\Percent;
use Testo\Core\Metric\Time;
use Testo\Metric\Metric;

/**
 * A benchmark's measurements as test metadata, named `bench.<case>.<columnGroup>.<column>`.
 *
 * The four-segment name spells a grid a consumer can rebuild as a table: `bench` names it, the case is
 * the row, and the last two segments place the value under a column-group band matching the ASCII table
 * the terminal prints (`Benchmark setup`, `Time results`, `Filtered results`, `Summary`). Every row
 * carries every column so the grid stays rectangular; a mixed depth would drop a consumer back to a
 * flat list. The same leaf may repeat across groups (`mean` under both `Time results` and
 * `Filtered results`); the group segment tells them apart.
 *
 * Values stay in the unit the plugin measured them in, time in microseconds; a reporter that needs
 * another converts at its own boundary.
 *
 * @internal
 */
final class BenchMetadata
{
    private function __construct() {}

    /**
     * @return list<MetadataEntry>
     */
    public static function of(BenchResult $result): array
    {
        $entries = [];

        /** @var Line $line */
        foreach ($result->lines as $line) {
            $set = self::caseSet($result, $line->name);
            $row = "bench.{$line->name}.";

            $entries[] = MetadataEntry::number($row . 'Benchmark setup.iters', \count($set?->iterations ?? []));
            $entries[] = MetadataEntry::number($row . 'Benchmark setup.calls', self::callsOf($set));

            $entries[] = MetadataEntry::number($row . 'Time results.mean', new Metric($line->avg->value, Time::Microseconds));
            $entries[] = MetadataEntry::number($row . 'Time results.meanDiff', new Metric($line->avg->diff, Percent::Percent));
            $entries[] = MetadataEntry::number($row . 'Time results.median', new Metric($line->med->value, Time::Microseconds));
            $entries[] = MetadataEntry::number($row . 'Time results.medianDiff', new Metric($line->med->diff, Percent::Percent));
            $entries[] = MetadataEntry::number($row . 'Time results.rstdev', new Metric($line->rstdev, Percent::Percent));

            $entries[] = MetadataEntry::number($row . 'Filtered results.rejected', $line->rejected);
            $entries[] = MetadataEntry::number($row . 'Filtered results.mean', new Metric($line->favg->value, Time::Microseconds));
            $entries[] = MetadataEntry::number($row . 'Filtered results.meanDiff', new Metric($line->favg->diff, Percent::Percent));
            $entries[] = MetadataEntry::number($row . 'Filtered results.rstdev', new Metric($line->frstdev, Percent::Percent));

            $entries[] = MetadataEntry::number($row . 'Summary.place', $line->place);
            $entries[] = MetadataEntry::number($row . 'Summary.memory', new Metric(self::memoryOf($set), Memory::Bytes));
        }

        return $entries;
    }

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
}
