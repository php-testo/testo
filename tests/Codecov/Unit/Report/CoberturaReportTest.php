<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Report;

use Testo\Assert;
use Testo\Codecov\Result\BranchCoverage;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\FileCoverage;
use Testo\Codecov\Result\FunctionCoverage;
use Testo\Codecov\Result\LineStatus;
use Testo\Codecov\Result\PathCoverage;
use Testo\Codecov\Report\CoberturaReport;
use Testo\Test;

#[Test]
final class CoberturaReportTest
{
    public function generatesValidXml(): void
    {
        // Arrange
        $result = new CoverageResult([
            '/project/src/Foo.php' => new FileCoverage('/project/src/Foo.php', [
                5 => LineStatus::Executed,
                6 => LineStatus::NotExecuted,
            ]),
        ]);
        $path = \sys_get_temp_dir() . '/testo_cobertura_' . \uniqid() . '.xml';

        // Act
        (new CoberturaReport($path, '/project'))->generate($result);

        // Assert
        $xml = \simplexml_load_file($path);
        Assert::notSame($xml, false);
        Assert::same((string) $xml['version'], '0.4');
        Assert::same((string) $xml['lines-covered'], '1');
        Assert::same((string) $xml['lines-valid'], '2');

        \unlink($path);
    }

    public function groupsFilesByPackage(): void
    {
        // Arrange
        $result = new CoverageResult([
            '/project/src/Core/Foo.php' => new FileCoverage('/project/src/Core/Foo.php', [
                5 => LineStatus::Executed,
            ]),
            '/project/src/Core/Bar.php' => new FileCoverage('/project/src/Core/Bar.php', [
                10 => LineStatus::Executed,
            ]),
            '/project/src/Http/Handler.php' => new FileCoverage('/project/src/Http/Handler.php', [
                3 => LineStatus::NotExecuted,
            ]),
        ]);
        $path = \sys_get_temp_dir() . '/testo_cobertura_' . \uniqid() . '.xml';

        // Act
        (new CoberturaReport($path, '/project'))->generate($result);

        // Assert
        $xml = \simplexml_load_file($path);
        $packages = $xml->packages->package;
        Assert::count($packages, 2);

        \unlink($path);
    }

    public function relativeFilenames(): void
    {
        // Arrange
        $result = new CoverageResult([
            '/project/src/Foo.php' => new FileCoverage('/project/src/Foo.php', [
                5 => LineStatus::Executed,
            ]),
        ]);
        $path = \sys_get_temp_dir() . '/testo_cobertura_' . \uniqid() . '.xml';

        // Act
        (new CoberturaReport($path, '/project'))->generate($result);

        // Assert
        $xml = \simplexml_load_file($path);
        $class = $xml->packages->package->classes->class;
        Assert::same((string) $class['filename'], 'src/Foo.php');
        Assert::same((string) $class['name'], 'Foo');

        \unlink($path);
    }

    public function branchDataFillsRates(): void
    {
        // Arrange — file with branch data
        $result = new CoverageResult([
            '/src/Foo.php' => new FileCoverage('/src/Foo.php', [
                5 => LineStatus::Executed,
                6 => LineStatus::NotExecuted,
            ], [
                'Foo->bar' => new FunctionCoverage('Foo->bar', [
                    0 => new BranchCoverage(0, 3, 5, 6, hit: true, out: [4, 7], outHit: [true, false]),
                ], [
                    new PathCoverage([0, 4], hit: true),
                    new PathCoverage([0, 7], hit: false),
                ]),
            ]),
        ]);
        $path = \sys_get_temp_dir() . '/testo_cobertura_' . \uniqid() . '.xml';

        // Act
        (new CoberturaReport($path, '/'))->generate($result);

        // Assert
        $xml = \simplexml_load_file($path);

        // Branch rate should be 0.5 (1 of 2 out_hit)
        Assert::same((string) $xml['branches-covered'], '1');
        Assert::same((string) $xml['branches-valid'], '2');

        // Line 5 should have branch="true" with condition-coverage
        $lines = $xml->packages->package->classes->class->lines->line;
        $branchLine = null;
        foreach ($lines as $line) {
            if ((string) $line['number'] === '5') {
                $branchLine = $line;
                break;
            }
        }

        Assert::notSame($branchLine, null);
        Assert::same((string) $branchLine['branch'], 'true');
        Assert::same((string) $branchLine['condition-coverage'], '50% (1/2)');

        \unlink($path);
    }

    public function noBranchDataProducesZeroBranchRate(): void
    {
        // Arrange
        $result = new CoverageResult([
            '/src/Foo.php' => new FileCoverage('/src/Foo.php', [
                5 => LineStatus::Executed,
            ]),
        ]);
        $path = \sys_get_temp_dir() . '/testo_cobertura_' . \uniqid() . '.xml';

        // Act
        (new CoberturaReport($path, '/'))->generate($result);

        // Assert
        $xml = \simplexml_load_file($path);
        Assert::same((string) $xml['branch-rate'], '0');
        Assert::same((string) $xml['branches-covered'], '0');
        Assert::same((string) $xml['branches-valid'], '0');

        \unlink($path);
    }
}
