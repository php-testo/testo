<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Report;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Codecov\Result\BranchCoverage;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\FileCoverage;
use Testo\Codecov\Result\FunctionCoverage;
use Testo\Codecov\Result\LineCoverage;
use Testo\Codecov\Result\LineStatus;
use Testo\Codecov\Report\CloverReport;
use Testo\Test;

#[Test]
#[Covers(CloverReport::class)]
final class CloverReportTest
{
    public function generatesValidXml(): void
    {
        $result = new CoverageResult([
            '/src/Foo.php' => new FileCoverage('/src/Foo.php', [
                5 => new LineCoverage(5, LineStatus::Executed),
                6 => new LineCoverage(6, LineStatus::NotExecuted),
                7 => new LineCoverage(7, LineStatus::Dead),
            ]),
        ]);
        $path = self::tmpPath();

        (new CloverReport($path, 'TestProject'))->generate($result);

        $xml = \simplexml_load_file($path);
        Assert::notSame($xml, false);
        Assert::same((string) $xml['generated'] !== '', true);
        Assert::same((string) $xml->project['name'], 'TestProject');

        \unlink($path);
    }

    public function countsStatementsCorrectly(): void
    {
        $result = new CoverageResult([
            '/src/Foo.php' => new FileCoverage('/src/Foo.php', [
                5 => new LineCoverage(5, LineStatus::Executed),
                6 => new LineCoverage(6, LineStatus::Executed),
                7 => new LineCoverage(7, LineStatus::NotExecuted),
                8 => new LineCoverage(8, LineStatus::Dead),
            ]),
        ]);
        $path = self::tmpPath();

        (new CloverReport($path))->generate($result);

        $xml = \simplexml_load_file($path);
        $metrics = $xml->project->metrics;
        Assert::same((string) $metrics['files'], '1');
        Assert::same((string) $metrics['statements'], '3');
        Assert::same((string) $metrics['coveredstatements'], '2');

        \unlink($path);
    }

    public function emptyResultProducesEmptyReport(): void
    {
        $path = self::tmpPath();

        (new CloverReport($path))->generate(new CoverageResult());

        $xml = \simplexml_load_file($path);
        Assert::same((string) $xml->project->metrics['files'], '0');
        Assert::same((string) $xml->project->metrics['statements'], '0');

        \unlink($path);
    }

    public function writesLineElements(): void
    {
        $result = new CoverageResult([
            '/src/Foo.php' => new FileCoverage('/src/Foo.php', [
                10 => new LineCoverage(10, LineStatus::Executed),
                20 => new LineCoverage(20, LineStatus::NotExecuted),
            ]),
        ]);
        $path = self::tmpPath();

        (new CloverReport($path))->generate($result);

        $xml = \simplexml_load_file($path);
        $lines = $xml->project->file->line;
        Assert::count($lines, 2);
        Assert::same((string) $lines[0]['num'], '10');
        Assert::same((string) $lines[0]['count'], '1');
        Assert::same((string) $lines[1]['num'], '20');
        Assert::same((string) $lines[1]['count'], '0');

        \unlink($path);
    }

    public function branchDataFillsConditionals(): void
    {
        $result = new CoverageResult([
            '/src/Foo.php' => new FileCoverage('/src/Foo.php', [
                5 => new LineCoverage(5, LineStatus::Executed),
                6 => new LineCoverage(6, LineStatus::NotExecuted),
            ], [
                'Foo->bar' => new FunctionCoverage('Foo->bar', [
                    0 => new BranchCoverage(0, 3, 5, 6, hit: true, out: [4, 7], outHit: [true, false]),
                ], []),
            ]),
        ]);
        $path = self::tmpPath();

        (new CloverReport($path))->generate($result);

        $xml = \simplexml_load_file($path);

        $fileMetrics = $xml->project->file->metrics;
        Assert::same((string) $fileMetrics['conditionals'], '2');
        Assert::same((string) $fileMetrics['coveredconditionals'], '1');

        $projectMetrics = $xml->project->metrics;
        Assert::same((string) $projectMetrics['conditionals'], '2');
        Assert::same((string) $projectMetrics['coveredconditionals'], '1');

        \unlink($path);
    }

    public function elementsIncludeConditionalsAlongsideStatements(): void
    {
        $result = new CoverageResult([
            '/src/Foo.php' => new FileCoverage('/src/Foo.php', [
                5 => new LineCoverage(5, LineStatus::Executed),
                6 => new LineCoverage(6, LineStatus::NotExecuted),
            ], [
                'Foo->bar' => new FunctionCoverage('Foo->bar', [
                    0 => new BranchCoverage(0, 3, 5, 6, hit: true, out: [4, 7], outHit: [true, false]),
                ], []),
            ]),
        ]);
        $path = self::tmpPath();

        (new CloverReport($path))->generate($result);

        $xml = \simplexml_load_file($path);
        $metrics = $xml->project->file->metrics;

        Assert::same((string) $metrics['elements'], '4');
        Assert::same((string) $metrics['coveredelements'], '2');

        \unlink($path);
    }

    public function writesConditionalLineTypeWithTrueAndFalseCounts(): void
    {
        $result = new CoverageResult([
            '/src/Foo.php' => new FileCoverage('/src/Foo.php', [
                5 => new LineCoverage(5, LineStatus::Executed),
            ], [
                'Foo->bar' => new FunctionCoverage('Foo->bar', [
                    0 => new BranchCoverage(0, 3, 5, 5, hit: true, out: [4, 7], outHit: [true, false]),
                ], []),
            ]),
        ]);
        $path = self::tmpPath();

        (new CloverReport($path))->generate($result);

        $xml = \simplexml_load_file($path);
        $line = $xml->project->file->line;

        Assert::same((string) $line['type'], 'cond');
        Assert::same((string) $line['truecount'], '1');
        Assert::same((string) $line['falsecount'], '1');
        Assert::false(isset($line['count']));

        \unlink($path);
    }

    public function branchesOnALineWithOnlyOneOutgoingEdgeStayPlainStatements(): void
    {
        $result = new CoverageResult([
            '/src/Foo.php' => new FileCoverage('/src/Foo.php', [
                5 => new LineCoverage(5, LineStatus::Executed),
            ], [
                'Foo->bar' => new FunctionCoverage('Foo->bar', [
                    0 => new BranchCoverage(0, 3, 5, 5, hit: true, out: [4], outHit: [true]),
                ], []),
            ]),
        ]);
        $path = self::tmpPath();

        (new CloverReport($path))->generate($result);

        $xml = \simplexml_load_file($path);
        $line = $xml->project->file->line;

        Assert::same((string) $line['type'], 'stmt');
        Assert::false(isset($line['truecount']));
        Assert::false(isset($line['falsecount']));

        $metrics = $xml->project->file->metrics;
        Assert::same((string) $metrics['conditionals'], '0');
        Assert::same((string) $metrics['coveredconditionals'], '0');

        \unlink($path);
    }

    public function noBranchDataKeepsConditionalsAtZero(): void
    {
        $result = new CoverageResult([
            '/src/Foo.php' => new FileCoverage('/src/Foo.php', [
                5 => new LineCoverage(5, LineStatus::Executed),
                6 => new LineCoverage(6, LineStatus::NotExecuted),
            ]),
        ]);
        $path = self::tmpPath();

        (new CloverReport($path))->generate($result);

        $xml = \simplexml_load_file($path);

        $fileMetrics = $xml->project->file->metrics;
        Assert::same((string) $fileMetrics['conditionals'], '0');
        Assert::same((string) $fileMetrics['coveredconditionals'], '0');
        Assert::same((string) $fileMetrics['elements'], (string) $fileMetrics['statements']);
        Assert::same((string) $fileMetrics['coveredelements'], (string) $fileMetrics['coveredstatements']);

        $projectMetrics = $xml->project->metrics;
        Assert::same((string) $projectMetrics['conditionals'], '0');
        Assert::same((string) $projectMetrics['coveredconditionals'], '0');

        foreach ($xml->project->file->line as $line) {
            Assert::same((string) $line['type'], 'stmt');
        }

        \unlink($path);
    }

    public function statesItsFormatAndTheFileItWrites(): void
    {
        $info = (new CloverReport('build/logs/clover.xml'))->info();

        // `.xml` alone would not tell Clover apart from Cobertura or the PHPUnit coverage XML.
        Assert::notNull($info);
        Assert::same($info->format, 'clover');
        Assert::same((string) $info->path, 'build/logs/clover.xml');
    }

    /**
     * Git-ignored scratch path inside this module's tests. Avoids
     * `sys_get_temp_dir()`, whose value can be a non-Windows path under some
     * agent runners and breaks `mkdir()`.
     */
    private static function tmpPath(): string
    {
        return \dirname(__DIR__, 2) . '/runtime/testo_clover_' . \uniqid() . '.xml';
    }
}
