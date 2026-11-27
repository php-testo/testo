<?php

declare(strict_types=1);

namespace Tests\Bench\Unit;

use Testo\Assert;
use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\Line;
use Testo\Bench\Dto\Report\HighVariance;
use Testo\Bench\Dto\Snap;
use Testo\Bench\Dto\ValueRel;
use Testo\Bench\Internal\BenchMetadata;
use Testo\Codecov\Covers;
use Testo\Core\Metadata\MetadataEntry;
use Testo\Core\Metadata\MetadataKind;
use Testo\Core\Metric\Memory;
use Testo\Core\Metric\Percent;
use Testo\Core\Metric\Scalar;
use Testo\Core\Metric\Time;
use Testo\Metric\Metric;
use Testo\Test;

#[Test]
#[Covers(BenchMetadata::class)]
final class BenchMetadataTest
{
    public function everyCaseCarriesEveryColumnOfTheGrid(): void
    {
        $columns = [
            'Benchmark setup.iters',
            'Benchmark setup.calls',
            'Time results.mean',
            'Time results.meanDiff',
            'Time results.median',
            'Time results.medianDiff',
            'Time results.rstdev',
            'Filtered results.rejected',
            'Filtered results.mean',
            'Filtered results.meanDiff',
            'Filtered results.rstdev',
            'Summary.place',
            'Summary.memory',
        ];

        $names = \array_map(
            static fn(MetadataEntry $entry): string => $entry->name,
            BenchMetadata::of(self::result()),
        );

        Assert::same($names, [
            ...\array_map(static fn(string $column): string => "bench.shift.{$column}", $columns),
            ...\array_map(static fn(string $column): string => "bench.push.{$column}", $columns),
        ]);
    }

    public function everyEntryIsANumber(): void
    {
        foreach (BenchMetadata::of(self::result()) as $entry) {
            Assert::same($entry->kind, MetadataKind::Number);
            Assert::instanceOf($entry->value, Metric::class);
        }
    }

    public function theUnitRidesOnTheMetricNotInTheName(): void
    {
        $metrics = self::metrics(self::result());

        // The dimension rides on the Metric so a consumer reports it natively; the leaf is unit-free.
        Assert::same($metrics['bench.shift.Time results.mean']->unit, Time::Microseconds);
        Assert::same($metrics['bench.shift.Time results.rstdev']->unit, Percent::Percent);
        Assert::same($metrics['bench.shift.Summary.memory']->unit, Memory::Bytes);
        Assert::same($metrics['bench.shift.Summary.place']->unit, Scalar::Number);
        Assert::same($metrics['bench.shift.Benchmark setup.iters']->unit, Scalar::Number);

        // The unit-bearing spellings must not leak back into the name.
        Assert::false(\array_key_exists('bench.shift.Time results.meanUs', $metrics));
        Assert::false(\array_key_exists('bench.shift.Summary.memoryBytes', $metrics));
    }

    public function timeStaysInItsNativeMicroseconds(): void
    {
        $metrics = self::metrics(self::result());

        // A consumer that needs another unit converts at its own boundary. The same leaf lives under two
        // bands; the group segment keeps them apart.
        Assert::same($metrics['bench.shift.Time results.mean']->value, 5.1);
        Assert::same($metrics['bench.shift.Filtered results.mean']->value, 5.05);
    }

    public function valuesComeFromTheRankedLinesAndTheirCases(): void
    {
        $metrics = self::metrics(self::result());

        Assert::same($metrics['bench.shift.Benchmark setup.iters']->value, 2);
        Assert::same($metrics['bench.shift.Benchmark setup.calls']->value, 20);
        Assert::same($metrics['bench.shift.Summary.place']->value, 1);
        Assert::same($metrics['bench.push.Summary.place']->value, 2);
        Assert::same($metrics['bench.push.Filtered results.rejected']->value, 1);
        Assert::same($metrics['bench.push.Time results.rstdev']->value, 12.0);
        Assert::same($metrics['bench.push.Time results.meanDiff']->value, 57.8);
        Assert::same($metrics['bench.push.Filtered results.rstdev']->value, 11.0);
    }

    public function memoryIsThePeakAcrossIterations(): void
    {
        $metrics = self::metrics(self::result());

        // Iterations of one case measure the same work, so the figure is the largest they reached, not
        // whichever iteration happened to be last.
        Assert::same($metrics['bench.shift.Summary.memory']->value, 300);
        Assert::same($metrics['bench.push.Summary.memory']->value, 0);
    }

    public function aLineWithoutItsCaseReportsZeroSetup(): void
    {
        $result = new BenchResult([], [], [self::line('orphan', 1)]);

        $metrics = self::metrics($result);

        Assert::same($metrics['bench.orphan.Benchmark setup.iters']->value, 0);
        Assert::same($metrics['bench.orphan.Benchmark setup.calls']->value, 0);
        Assert::same($metrics['bench.orphan.Summary.memory']->value, 0);
    }

    public function anEmptyResultReportsNothing(): void
    {
        Assert::same(BenchMetadata::of(new BenchResult([], [])), []);
    }

    /**
     * @return array<non-empty-string, Metric>
     */
    private static function metrics(BenchResult $result): array
    {
        $metrics = [];
        foreach (BenchMetadata::of($result) as $entry) {
            \assert($entry->value instanceof Metric);
            $metrics[$entry->name] = $entry->value;
        }

        return $metrics;
    }

    /**
     * Two cases across two iterations: a baseline `shift` and a slower, noisier `push` that earns a
     * diagnostic. `push` allocates nothing, `shift` peaks at 300 bytes on its second iteration.
     */
    private static function result(): BenchResult
    {
        $cases = [
            new CaseSet('shift', [
                new Snap(calls: 20, memory: 100, time: 5.0),
                new Snap(calls: 20, memory: 300, time: 5.2),
            ]),
            new CaseSet('push', [
                new Snap(calls: 20, memory: 0, time: 8.0),
                new Snap(calls: 20, memory: 0, time: 8.1),
            ]),
        ];

        $lines = [
            new Line(
                place: 1,
                name: 'shift',
                avg: new ValueRel(5.1, 0.0),
                med: new ValueRel(5.1, 0.0),
                rstdev: 2.0,
                favg: new ValueRel(5.05, 0.0),
                frstdev: 1.5,
                rejected: 0,
                reports: [],
            ),
            new Line(
                place: 2,
                name: 'push',
                avg: new ValueRel(8.05, 57.8),
                med: new ValueRel(8.05, 57.8),
                rstdev: 12.0,
                favg: new ValueRel(8.0, 58.4),
                frstdev: 11.0,
                rejected: 1,
                reports: [new HighVariance(12.0)],
            ),
        ];

        return new BenchResult($cases, [], $lines);
    }

    private static function line(string $name, int $place): Line
    {
        return new Line(
            place: $place,
            name: $name,
            avg: new ValueRel(1.0, 0.0),
            med: new ValueRel(1.0, 0.0),
            rstdev: 0.0,
            favg: new ValueRel(1.0, 0.0),
            frstdev: 0.0,
            rejected: 0,
            reports: [],
        );
    }
}
