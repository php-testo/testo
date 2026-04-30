<?php

declare(strict_types=1);

namespace Testo\Codecov\Report;

use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\FileCoverage;
use Testo\Codecov\Result\LineStatus;

/**
 * Generates a Clover XML coverage report.
 *
 * Compatible with CI tools such as SonarQube, Codecov.io, and Atlassian Clover.
 *
 * @api
 */
final readonly class CloverReport implements CoverageReport
{
    public function __construct(
        /** @var non-empty-string Output file path. */
        private string $outputPath,
        private string $projectName = '',
    ) {}

    #[\Override]
    public function generate(CoverageResult $result): void
    {
        $timestamp = \time();

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('coverage');
        $xml->writeAttribute('generated', (string) $timestamp);

        $xml->startElement('project');
        $xml->writeAttribute('timestamp', (string) $timestamp);
        $this->projectName !== '' and $xml->writeAttribute('name', $this->projectName);

        $totalStatements = 0;
        $totalCovered = 0;
        $fileCount = 0;

        foreach ($result->files as $fileCoverage) {
            [$statements, $covered] = $this->writeFile($xml, $fileCoverage);
            $totalStatements += $statements;
            $totalCovered += $covered;
            $fileCount++;
        }

        // Project-level metrics
        $this->writeMetrics($xml, [
            'files' => $fileCount,
            'statements' => $totalStatements,
            'coveredstatements' => $totalCovered,
            'elements' => $totalStatements,
            'coveredelements' => $totalCovered,
            'conditionals' => 0,
            'coveredconditionals' => 0,
        ]);

        $xml->endElement(); // project
        $xml->endElement(); // coverage
        $xml->endDocument();

        $dir = \dirname($this->outputPath);
        \is_dir($dir) or \mkdir($dir, 0o755, true);
        \file_put_contents($this->outputPath, $xml->outputMemory());
    }

    /**
     * @return array{int<0, max>, int<0, max>} [statements, covered]
     */
    private function writeFile(\XMLWriter $xml, FileCoverage $fileCoverage): array
    {
        $xml->startElement('file');
        $xml->writeAttribute('name', $fileCoverage->path);

        $statements = 0;
        $covered = 0;

        // Sort lines by number
        $lines = $fileCoverage->lines;
        \ksort($lines);

        foreach ($lines as $lineNumber => $line) {
            if (!$line->status->isExecutable()) {
                continue;
            }

            $count = $line->status === LineStatus::Executed ? 1 : 0;
            $statements++;
            $covered += $count;

            $xml->startElement('line');
            $xml->writeAttribute('num', (string) $lineNumber);
            $xml->writeAttribute('type', 'stmt');
            $xml->writeAttribute('count', (string) $count);
            $xml->endElement();
        }

        $this->writeMetrics($xml, [
            'statements' => $statements,
            'coveredstatements' => $covered,
            'elements' => $statements,
            'coveredelements' => $covered,
            'conditionals' => 0,
            'coveredconditionals' => 0,
        ]);

        $xml->endElement(); // file

        return [$statements, $covered];
    }

    /**
     * @param array<string, int> $values
     */
    private function writeMetrics(\XMLWriter $xml, array $values): void
    {
        $xml->startElement('metrics');
        foreach ($values as $name => $value) {
            $xml->writeAttribute($name, (string) $value);
        }
        $xml->endElement();
    }
}
