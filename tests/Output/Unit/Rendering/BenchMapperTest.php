<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Rendering;

use Testo\Assert;
use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\Line;
use Testo\Bench\Dto\Report\HighVariance;
use Testo\Bench\Dto\Snap;
use Testo\Bench\Dto\ValueRel;
use Testo\Codecov\Covers;
use Testo\Output\Rendering\BenchMapper;
use Testo\Test;

#[Test]
#[Covers(BenchMapper::class)]
final class BenchMapperTest
{
    public function supportsRecognisesOnlyABenchResult(): void
    {
        Assert::true(BenchMapper::supports(self::result()));
        Assert::false(BenchMapper::supports(null));
        Assert::false(BenchMapper::supports('bench'));
        Assert::false(BenchMapper::supports(new \stdClass()));
    }

    public function mapReportsIterationCountFromTheFirstCase(): void
    {
        $map = BenchMapper::map(self::result());

        Assert::same($map['iterations'], 2);
    }

    public function mapCarriesEveryRankedFigureOfACase(): void
    {
        $baseline = BenchMapper::map(self::result())['cases'][0];

        Assert::same($baseline['name'], 'shift');
        Assert::same($baseline['place'], 1);
        Assert::same($baseline['calls'], 20);
        Assert::same($baseline['mean'], 5.1);
        Assert::same($baseline['median'], 5.1);
        Assert::same($baseline['meanDiff'], 0.0);
        Assert::same($baseline['rstdev'], 2.0);
        Assert::same($baseline['filteredMean'], 5.05);
        Assert::same($baseline['rejected'], 0);
    }

    public function mapTakesThePeakMemoryAcrossIterations(): void
    {
        // Iterations of one case measure the same work, so the figure is the largest they reached, not
        // whichever iteration happened to be last.
        $baseline = BenchMapper::map(self::result())['cases'][0];

        Assert::same($baseline['memory'], 300);
    }

    public function mapFlattensDiagnosticsWithTheShortReportName(): void
    {
        $diagnostics = BenchMapper::map(self::result())['diagnostics'];

        Assert::count($diagnostics, 1);
        Assert::same($diagnostics[0]['case'], 'push');
        Assert::same($diagnostics[0]['kind'], 'HighVariance');
        Assert::same($diagnostics[0]['severity'], 'warning');
        Assert::same($diagnostics[0]['reason'], 'High variance');
        Assert::string($diagnostics[0]['advice'])->contains('Increase');
    }

    public function metricsNameTheUnitInEveryKey(): void
    {
        $metrics = BenchMapper::metrics(self::result());

        // A flat namespace has nowhere else to record the unit, so it lives in the key. The unit-less
        // spelling must not leak through alongside it.
        Assert::true(\array_key_exists('bench.shift.meanUs', $metrics));
        Assert::false(\array_key_exists('bench.shift.mean', $metrics));
        Assert::true(\array_key_exists('bench.shift.rstdevPct', $metrics));
        Assert::true(\array_key_exists('bench.shift.memoryBytes', $metrics));
    }

    public function metricsCarryTheSameNumbersAsTheMap(): void
    {
        $metrics = BenchMapper::metrics(self::result());

        Assert::same($metrics['bench.iterations'], 2);
        Assert::same($metrics['bench.shift.calls'], 20);
        Assert::same($metrics['bench.shift.place'], 1);
        Assert::same($metrics['bench.shift.meanUs'], 5.1);
        Assert::same($metrics['bench.shift.memoryBytes'], 300);
        Assert::same($metrics['bench.push.memoryBytes'], 0);
        Assert::same($metrics['bench.push.rejected'], 1);
        Assert::same($metrics['bench.push.rstdevPct'], 12.0);
    }

    public function anEmptyResultProducesNoCasesAndNoDiagnostics(): void
    {
        $map = BenchMapper::map(new BenchResult([], []));

        Assert::same($map['iterations'], 0);
        Assert::same($map['cases'], []);
        Assert::same($map['diagnostics'], []);
    }

    public function anEmptyResultStillReportsItsIterationCounter(): void
    {
        Assert::same(BenchMapper::metrics(new BenchResult([], [])), ['bench.iterations' => 0]);
    }

    public function formatMetricKeepsAnIntegerExact(): void
    {
        Assert::same(BenchMapper::formatMetric(20), '20');
        Assert::same(BenchMapper::formatMetric(0), '0');
    }

    public function formatMetricTrimsTrailingZerosOfAFloat(): void
    {
        Assert::same(BenchMapper::formatMetric(5.1), '5.1');
        Assert::same(BenchMapper::formatMetric(0.25), '0.25');
        // A whole-valued float loses its fractional part rather than reading as `3.000000`.
        Assert::same(BenchMapper::formatMetric(3.0), '3');
    }

    public function formatMetricWritesANearZeroFloatInFixedNotation(): void
    {
        // Fixed notation, not `1.0E-6`, which a JUnit consumer or a TeamCity `type='number'` would have
        // to special-case.
        Assert::same(BenchMapper::formatMetric(0.000001), '0.000001');
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
}
