<?php

declare(strict_types=1);

namespace Testo\Codecov\Report;

use Testo\Codecov\Dto\CoverageResult;
use Testo\Codecov\Dto\FileCoverage;
use Testo\Codecov\Dto\LineStatus;

/**
 * Generates a Cobertura XML coverage report.
 *
 * Compatible with CI tools such as GitHub Actions, GitLab CI, and Jenkins.
 *
 * @api
 */
final readonly class CoberturaReport implements CoverageReport
{
    public function __construct(
        /** @var non-empty-string Output file path. */
        private string $outputPath,
        /** @var non-empty-string Source root for relative paths. */
        private string $sourceRoot = '',
    ) {}

    #[\Override]
    public function generate(CoverageResult $result): void
    {
        $sourceRoot = $this->sourceRoot !== '' ? $this->sourceRoot : (string) \getcwd();
        $sourceRoot = \rtrim(\str_replace('\\', '/', $sourceRoot), '/');

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        $xml->startDocument('1.0', 'UTF-8');
        $xml->writeDtd('coverage', null, 'http://cobertura.sourceforge.net/xml/coverage-04.dtd');

        // Group files by directory into packages
        $packages = $this->groupByPackage($result, $sourceRoot);

        // Calculate totals
        $totalStatements = 0;
        $totalCovered = 0;
        foreach ($result->files as $fileCoverage) {
            [$s, $c] = self::countLines($fileCoverage);
            $totalStatements += $s;
            $totalCovered += $c;
        }

        $xml->startElement('coverage');
        $xml->writeAttribute('line-rate', self::rate($totalCovered, $totalStatements));
        $xml->writeAttribute('branch-rate', '0');
        $xml->writeAttribute('lines-covered', (string) $totalCovered);
        $xml->writeAttribute('lines-valid', (string) $totalStatements);
        $xml->writeAttribute('branches-covered', '0');
        $xml->writeAttribute('branches-valid', '0');
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
        $pkgStatements = 0;
        $pkgCovered = 0;
        foreach ($files as $file) {
            [$s, $c] = self::countLines($file['coverage']);
            $pkgStatements += $s;
            $pkgCovered += $c;
        }

        $xml->startElement('package');
        $xml->writeAttribute('name', $packageName);
        $xml->writeAttribute('line-rate', self::rate($pkgCovered, $pkgStatements));
        $xml->writeAttribute('branch-rate', '0');
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

        // Use filename without extension as class name
        $className = \basename($relativePath, '.php');

        $xml->startElement('class');
        $xml->writeAttribute('name', $className);
        $xml->writeAttribute('filename', $relativePath);
        $xml->writeAttribute('line-rate', self::rate($covered, $statements));
        $xml->writeAttribute('branch-rate', '0');
        $xml->writeAttribute('complexity', '0');

        $xml->startElement('lines');

        $lines = $fileCoverage->lines;
        \ksort($lines);

        foreach ($lines as $lineNumber => $status) {
            if (!$status->isExecutable()) {
                continue;
            }

            $xml->startElement('line');
            $xml->writeAttribute('number', (string) $lineNumber);
            $xml->writeAttribute('hits', $status === LineStatus::Executed ? '1' : '0');
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

        foreach ($fileCoverage->lines as $status) {
            if (!$status->isExecutable()) {
                continue;
            }

            $statements++;
            $status === LineStatus::Executed and $covered++;
        }

        return [$statements, $covered];
    }

    private static function rate(int $covered, int $total): string
    {
        return $total === 0 ? '0' : \sprintf('%.4f', $covered / $total);
    }
}
