<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Report;

use Testo\Assert;
use Testo\Codecov\Dto\CoverageResult;
use Testo\Codecov\Dto\FileCoverage;
use Testo\Codecov\Dto\LineStatus;
use Testo\Codecov\Report\CloverReport;
use Testo\Test;

#[Test]
final class CloverReportTest
{
    public function generatesValidXml(): void
    {
        // Arrange
        $result = new CoverageResult([
            '/src/Foo.php' => new FileCoverage('/src/Foo.php', [
                5 => LineStatus::Executed,
                6 => LineStatus::NotExecuted,
                7 => LineStatus::Dead,
            ]),
        ]);
        $path = \sys_get_temp_dir() . '/testo_clover_' . \uniqid() . '.xml';

        // Act
        (new CloverReport($path, 'TestProject'))->generate($result);

        // Assert
        $xml = \simplexml_load_file($path);
        Assert::notSame($xml, false);
        Assert::same((string) $xml['generated'] !== '', true);
        Assert::same((string) $xml->project['name'], 'TestProject');

        \unlink($path);
    }

    public function countsStatementsCorrectly(): void
    {
        // Arrange
        $result = new CoverageResult([
            '/src/Foo.php' => new FileCoverage('/src/Foo.php', [
                5 => LineStatus::Executed,
                6 => LineStatus::Executed,
                7 => LineStatus::NotExecuted,
                8 => LineStatus::Dead,
            ]),
        ]);
        $path = \sys_get_temp_dir() . '/testo_clover_' . \uniqid() . '.xml';

        // Act
        (new CloverReport($path))->generate($result);

        // Assert
        $xml = \simplexml_load_file($path);
        $metrics = $xml->project->metrics;
        Assert::same((string) $metrics['files'], '1');
        Assert::same((string) $metrics['statements'], '3');
        Assert::same((string) $metrics['coveredstatements'], '2');

        \unlink($path);
    }

    public function emptyResultProducesEmptyReport(): void
    {
        // Arrange
        $path = \sys_get_temp_dir() . '/testo_clover_' . \uniqid() . '.xml';

        // Act
        (new CloverReport($path))->generate(new CoverageResult());

        // Assert
        $xml = \simplexml_load_file($path);
        Assert::same((string) $xml->project->metrics['files'], '0');
        Assert::same((string) $xml->project->metrics['statements'], '0');

        \unlink($path);
    }

    public function writesLineElements(): void
    {
        // Arrange
        $result = new CoverageResult([
            '/src/Foo.php' => new FileCoverage('/src/Foo.php', [
                10 => LineStatus::Executed,
                20 => LineStatus::NotExecuted,
            ]),
        ]);
        $path = \sys_get_temp_dir() . '/testo_clover_' . \uniqid() . '.xml';

        // Act
        (new CloverReport($path))->generate($result);

        // Assert
        $xml = \simplexml_load_file($path);
        $lines = $xml->project->file->line;
        Assert::count($lines, 2);
        Assert::same((string) $lines[0]['num'], '10');
        Assert::same((string) $lines[0]['count'], '1');
        Assert::same((string) $lines[1]['num'], '20');
        Assert::same((string) $lines[1]['count'], '0');

        \unlink($path);
    }
}
