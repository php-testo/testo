<?php

declare(strict_types=1);

namespace Tests\Bench\Unit;

use Testo\Assert;
use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\CaseResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\Line;
use Testo\Bench\Dto\Report;
use Testo\Bench\Dto\Snap;
use Testo\Bench\Dto\ValueRel;
use Testo\Bench\Internal\Renderer;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Test;

#[Test]
#[Covers(Renderer::class)]
final class BenchTableTest
{
    public function rowsFollowDeclarationOrderKeepingCurrentFirst(): void
    {
        $out = Renderer::table(self::result());

        Assert::true(\strpos($out, 'slow') < \strpos($out, 'fast'));
    }

    public function callsAreAttributedPerCaseNotByName(): void
    {
        $out = Renderer::table(self::result());
        $lines = \explode("\n", $out);

        $fastRow = self::rowContaining($lines, 'fast');
        $slowRow = self::rowContaining($lines, 'slow');

        Assert::string($fastRow)->contains('200');
        Assert::string($slowRow)->contains('100');
    }

    public function noLinesRenderNothing(): void
    {
        Assert::same(Renderer::table(new BenchResult(cases: [], results: [], lines: [])), '');
    }

    public function rejectedResultsAddFilteredColumns(): void
    {
        $value = new ValueRel(100.0, 0.0);
        $filtered = new ValueRel(90.0, 0.0);
        $cases = [
            new CaseSet('alpha', [new Snap(10, 0, 1000.0), new Snap(10, 0, 1000.0)]),
            new CaseSet('beta', [new Snap(10, 0, 1000.0), new Snap(10, 0, 1000.0)]),
        ];
        $lines = [
            new Line(
                place: 3,
                name: 'alpha',
                avg: $value,
                med: $value,
                rstdev: 5.0,
                favg: $filtered,
                frstdev: 2.0,
                rejected: 7,
                reports: [],
            ),
            new Line(
                place: 4,
                name: 'beta',
                avg: $value,
                med: $value,
                rstdev: 5.0,
                favg: $filtered,
                frstdev: 2.0,
                rejected: 0,
                reports: [],
            ),
        ];

        $out = Renderer::table(new BenchResult(cases: $cases, results: [], lines: $lines));
        $rows = \explode("\n", $out);
        $alpha = self::rowContaining($rows, 'alpha');
        $beta = self::rowContaining($rows, 'beta');

        Assert::string($out)->contains('FILTERED RESULTS');
        Assert::string($out)->contains('Rej.');
        Assert::string($out)->contains('Mean*');
        Assert::string($out)->contains('RStDev*');
        # Filtered mean and rstdev columns render for the rejected line.
        Assert::string($alpha)->contains('90.00µs');
        Assert::string($alpha)->contains('±2.00%');
        Assert::string($alpha)->contains('7');
        # A line with no rejected outliers leaves its Rej. cell blank.
        Assert::same(\substr_count($beta, '90.00µs'), 1);
        # Ordinals for the rd and default-th suffix arms.
        Assert::string($alpha)->contains('3rd');
        Assert::string($beta)->contains('4th');
    }

    public function warningAndDangerReportsAddSummaryColumns(): void
    {
        $value = new ValueRel(100.0, 0.0);
        $cases = [new CaseSet('alpha', [new Snap(10, 0, 100.0)])];
        $line = new Line(
            place: 1,
            name: 'alpha',
            avg: $value,
            med: $value,
            rstdev: 0.0,
            favg: $value,
            frstdev: 0.0,
            rejected: 0,
            reports: [
                new Report\InsufficientIterTime(15.0, 5.0),
                new Report\NoisyEnvironment(15.0, 0),
                new Report\HeavilySkewed(40.0),
                new Report\LowIterTime(5.0),
            ],
        );

        $out = Renderer::table(new BenchResult(cases: $cases, results: [], lines: [$line]));

        Assert::string($out)->contains('SUMMARY');
        Assert::string($out)->contains('Warnings');
        Assert::string($out)->contains('Dangers');
        # Same-severity reasons join with '. ' in their column.
        Assert::string($out)->contains('Insufficient iter time. Noisy environment');
        Assert::string($out)->contains('Heavily skewed');
    }

    public function roundsWithNoCasesRenderNothing(): void
    {
        Assert::same(Renderer::rounds(new BenchResult(cases: [], results: [])), '');
    }

    public function roundsRendersEveryIterationWithNameOnFirstRowOnly(): void
    {
        $case = new CaseSet('bench', [
            new Snap(4, 2048, 20.0),
            new Snap(4, 4096, 40.0),
        ]);

        $out = Renderer::rounds(new BenchResult(cases: [$case], results: []));
        $rows = \explode("\n", $out);

        Assert::string($out)->contains('Iter');
        Assert::string($out)->contains('Time avg');
        Assert::string($out)->contains('Memory');
        # The case name labels only its first iteration row.
        Assert::same(\count(\array_filter($rows, static fn(string $l): bool => \str_contains($l, 'bench'))), 1);
        # Time avg is per-call: 20/4 and 40/4 microseconds.
        Assert::string($out)->contains('5.00µs');
        Assert::string($out)->contains('10.00µs');
        Assert::string($out)->contains('2.00 KB');
        Assert::string($out)->contains('4.00 KB');
    }

    public function roundsRendersZeroTimeAndMemoryAsBareZero(): void
    {
        $case = new CaseSet('z', [new Snap(1, 0, 0.0)]);

        $out = Renderer::rounds(new BenchResult(cases: [$case], results: []));
        $row = self::rowContaining(\explode("\n", $out), 'z');

        # Time, Time avg and Memory each collapse to a bare '0'.
        Assert::same(\substr_count($row, '| 0 '), 3);
    }

    #[DataSet([0.5, 512, '500.00ns', '512 B'], 'sub-microsecond -> ns, sub-kibibyte -> bytes')]
    #[DataSet([5.0, 2048, '5.00µs', '2.00 KB'], 'microseconds and kibibytes')]
    #[DataSet([5000.0, 3145728, '5.00ms', '3.00 MB'], 'milliseconds and mebibytes')]
    #[DataSet([2000000.0, 2147483648, '2.00s', '2.00 GB'], 'seconds and gibibytes')]
    public function roundsFormatsTimeAndMemoryMagnitudes(
        float $time,
        int $memory,
        string $timeToken,
        string $memoryToken,
    ): void {
        $case = new CaseSet('m', [new Snap(1, $memory, $time)]);

        $out = Renderer::rounds(new BenchResult(cases: [$case], results: []));

        Assert::string($out)->contains($timeToken);
        Assert::string($out)->contains($memoryToken);
    }

    /**
     * @param list<string> $lines
     */
    private static function rowContaining(array $lines, string $needle): string
    {
        foreach ($lines as $line) {
            if (\str_contains($line, $needle)) {
                return $line;
            }
        }

        return '';
    }

    private static function result(): BenchResult
    {
        $slowCase = new CaseSet('slow', [new Snap(100, 0, 200.0), new Snap(100, 0, 200.0)]);
        $fastCase = new CaseSet('fast', [new Snap(200, 0, 100.0), new Snap(200, 0, 100.0)]);

        $slowLine = self::line(place: 2, name: 'slow', time: 2.0);
        $fastLine = self::line(place: 1, name: 'fast', time: 1.0);

        # Lines are declared slow-first; their keys index into $cases.
        return new BenchResult(
            cases: [$slowCase, $fastCase],
            results: [],
            lines: [$slowLine, $fastLine],
        );
    }

    private static function line(int $place, string $name, float $time): Line
    {
        $value = new ValueRel($time, 0.0);

        return new Line(
            place: $place,
            name: $name,
            avg: $value,
            med: $value,
            rstdev: 0.0,
            favg: $value,
            frstdev: 0.0,
            rejected: 0,
            reports: [],
        );
    }
}
