<?php

declare(strict_types=1);

namespace Testo\Codecov\Report;

use Internal\Path;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\FileCoverage;
use Testo\Codecov\Result\LineStatus;

/**
 * Generates a Cobertura XML coverage report.
 *
 * When branch/path data is available, fills in branch-rate and per-line condition coverage.
 * Compatible with CI tools such as GitHub Actions, GitLab CI, and Jenkins.
 *
 * @api
 */
final readonly class CoberturaReport implements CoverageReport
{
    public function __construct(
        /** @var non-empty-string Output file path. */
        private string $outputPath,
    ) {}

    #[\Override]
    public function generate(CoverageResult $result): void
    {
        $sourceRoot = (string) Path::create($result->sourceRoot ?? '.');

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        $xml->startDocument('1.0', 'UTF-8');
        $xml->writeDtd('coverage', null, 'http://cobertura.sourceforge.net/xml/coverage-04.dtd');

        // Group files by directory into packages
        $packages = $this->groupByPackage($result, $sourceRoot);

        // Calculate totals
        $totalLines = 0;
        $totalLinesCovered = 0;
        $totalBranches = 0;
        $totalBranchesCovered = 0;
        foreach ($result->files as $fileCoverage) {
            [$s, $c] = self::countLines($fileCoverage);
            $totalLines += $s;
            $totalLinesCovered += $c;
            [$b, $bc] = self::countBranches($fileCoverage);
            $totalBranches += $b;
            $totalBranchesCovered += $bc;
        }

        $xml->startElement('coverage');
        $xml->writeAttribute('line-rate', self::rate($totalLinesCovered, $totalLines));
        $xml->writeAttribute('branch-rate', self::rate($totalBranchesCovered, $totalBranches));
        $xml->writeAttribute('lines-covered', (string) $totalLinesCovered);
        $xml->writeAttribute('lines-valid', (string) $totalLines);
        $xml->writeAttribute('branches-covered', (string) $totalBranchesCovered);
        $xml->writeAttribute('branches-valid', (string) $totalBranches);
        $xml->writeAttribute('complexity', '0');
        $xml->writeAttribute('version', '0.4');
        $xml->writeAttribute('timestamp', (string) \time());

        // Sources
        $xml->startElement('sources');
        $xml->writeElement('source', $sourceRoot);
        $xml->endElement();

        // Packages
        $xml->startElement('packages');
        foreach ($packages as $packageName => $files) {
            $this->writePackage($xml, $packageName, $files);
        }
        $xml->endElement(); // packages

        $xml->endElement(); // coverage
        $xml->endDocument();

        $dir = \dirname($this->outputPath);
        \is_dir($dir) or \mkdir($dir, 0o755, true);
        \file_put_contents($this->outputPath, $xml->outputMemory());
    }

    /**
     * Groups files by their relative directory path.
     *
     * @return array<string, list<array{relative: string, coverage: FileCoverage}>>
     */
    private function groupByPackage(CoverageResult $result, string $sourceRoot): array
    {
        $packages = [];

        foreach ($result->files as $fileCoverage) {
            $normalized = \str_replace('\\', '/', $fileCoverage->path);
            $relative = \str_starts_with($normalized, $sourceRoot . '/')
                ? \substr($normalized, \strlen($sourceRoot) + 1)
                : $normalized;

            $packageName = \dirname($relative);
            $packageName === '.' and $packageName = '';

            $packages[$packageName][] = ['relative' => $relative, 'coverage' => $fileCoverage];
        }

        \ksort($packages);

        return $packages;
    }

    /**
     * @param list<array{relative: string, coverage: FileCoverage}> $files
     */
    private function writePackage(\XMLWriter $xml, string $packageName, array $files): void
    {
        $pkgLines = 0;
        $pkgLinesCovered = 0;
        $pkgBranches = 0;
        $pkgBranchesCovered = 0;
        foreach ($files as $file) {
            [$s, $c] = self::countLines($file['coverage']);
            $pkgLines += $s;
            $pkgLinesCovered += $c;
            [$b, $bc] = self::countBranches($file['coverage']);
            $pkgBranches += $b;
            $pkgBranchesCovered += $bc;
        }

        $xml->startElement('package');
        $xml->writeAttribute('name', $packageName);
        $xml->writeAttribute('line-rate', self::rate($pkgLinesCovered, $pkgLines));
        $xml->writeAttribute('branch-rate', self::rate($pkgBranchesCovered, $pkgBranches));
        $xml->writeAttribute('complexity', '0');

        $xml->startElement('classes');
        foreach ($files as $file) {
            $this->writeClass($xml, $file['relative'], $file['coverage']);
        }
        $xml->endElement(); // classes

        $xml->endElement(); // package
    }

    private function writeClass(\XMLWriter $xml, string $relativePath, FileCoverage $fileCoverage): void
    {
        [$statements, $covered] = self::countLines($fileCoverage);
        [$branches, $branchesCovered] = self::countBranches($fileCoverage);

        $className = \basename($relativePath, '.php');

        $xml->startElement('class');
        $xml->writeAttribute('name', $className);
        $xml->writeAttribute('filename', $relativePath);
        $xml->writeAttribute('line-rate', self::rate($covered, $statements));
        $xml->writeAttribute('branch-rate', self::rate($branchesCovered, $branches));
        $xml->writeAttribute('complexity', '0');

        // Build per-line branch map
        $lineBranches = self::buildLineBranchMap($fileCoverage);

        $xml->startElement('lines');

        $lines = $fileCoverage->lines;
        \ksort($lines);

        foreach ($lines as $lineNumber => $line) {
            if (!$line->status->isExecutable()) {
                continue;
            }

            $xml->startElement('line');
            $xml->writeAttribute('number', (string) $lineNumber);
            $xml->writeAttribute('hits', $line->status === LineStatus::Executed ? '1' : '0');

            if (isset($lineBranches[$lineNumber])) {
                [$brTotal, $brCovered] = $lineBranches[$lineNumber];
                $xml->writeAttribute('branch', 'true');
                $pct = $brTotal > 0 ? (int) (100 * $brCovered / $brTotal) : 0;
                $xml->writeAttribute('condition-coverage', \sprintf('%d%% (%d/%d)', $pct, $brCovered, $brTotal));
            }

            $xml->endElement();
        }

        $xml->endElement(); // lines
        $xml->endElement(); // class
    }

    /**
     * @return array{int<0, max>, int<0, max>} [statements, covered]
     */
    private static function countLines(FileCoverage $fileCoverage): array
    {
        $statements = 0;
        $covered = 0;

        foreach ($fileCoverage->lines as $line) {
            if (!$line->status->isExecutable()) {
                continue;
            }

            $statements++;
            $line->status === LineStatus::Executed and $covered++;
        }

        return [$statements, $covered];
    }

    /**
     * @return array{int<0, max>, int<0, max>} [total branches, covered branches]
     */
    private static function countBranches(FileCoverage $fileCoverage): array
    {
        $total = 0;
        $covered = 0;

        foreach ($fileCoverage->functions as $function) {
            foreach ($function->branches as $branch) {
                $total += \count($branch->outHit);
                $covered += \count(\array_filter($branch->outHit));
            }
        }

        return [$total, $covered];
    }

    /**
     * Builds a map of line number => [total_branches, covered_branches]
     * for lines that are branch decision points.
     *
     * @return array<int, array{int<0, max>, int<0, max>}>
     */
    private static function buildLineBranchMap(FileCoverage $fileCoverage): array
    {
        $map = [];

        foreach ($fileCoverage->functions as $function) {
            foreach ($function->branches as $branch) {
                // Only mark lines with multiple outgoing edges as branch points
                if (\count($branch->out) < 2) {
                    continue;
                }

                $line = $branch->lineStart;
                $total = \count($branch->outHit);
                $covered = \count(\array_filter($branch->outHit));

                if (!isset($map[$line])) {
                    $map[$line] = [0, 0];
                }

                $map[$line][0] += $total;
                $map[$line][1] += $covered;
            }
        }

        return $map;
    }

    private static function rate(int $covered, int $total): string
    {
        return $total === 0 ? '0' : \sprintf('%.4f', $covered / $total);
    }
}
