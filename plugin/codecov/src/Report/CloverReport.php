<?php

declare(strict_types=1);

namespace Testo\Codecov\Report;

use Internal\Path;
use Testo\Codecov\Internal\BranchCoverageAggregator;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\FileCoverage;
use Testo\Codecov\Result\LineStatus;
use Testo\Core\Report\ReportInfo;

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
        $totalConditionals = 0;
        $totalCoveredConditionals = 0;
        $fileCount = 0;

        foreach ($result->files as $fileCoverage) {
            [$statements, $covered, $conditionals, $coveredConditionals] = $this->writeFile($xml, $fileCoverage);
            $totalStatements += $statements;
            $totalCovered += $covered;
            $totalConditionals += $conditionals;
            $totalCoveredConditionals += $coveredConditionals;
            $fileCount++;
        }

        // Project-level metrics
        $this->writeMetrics($xml, [
            'files' => $fileCount,
            'statements' => $totalStatements,
            'coveredstatements' => $totalCovered,
            'conditionals' => $totalConditionals,
            'coveredconditionals' => $totalCoveredConditionals,
            'elements' => $totalStatements + $totalConditionals,
            'coveredelements' => $totalCovered + $totalCoveredConditionals,
        ]);

        $xml->endElement(); // project
        $xml->endElement(); // coverage
        $xml->endDocument();

        $dir = \dirname($this->outputPath);
        \is_dir($dir) or \mkdir($dir, 0o755, true);
        \file_put_contents($this->outputPath, $xml->outputMemory());
    }

    #[\Override]
    public function info(): ReportInfo
    {
        return new ReportInfo('clover', 'Clover coverage', Path::create($this->outputPath));
    }

    /**
     * @return array{int<0, max>, int<0, max>, int<0, max>, int<0, max>}
     *         [statements, covered, conditionals, covered conditionals]
     */
    private function writeFile(\XMLWriter $xml, FileCoverage $fileCoverage): array
    {
        $xml->startElement('file');
        $xml->writeAttribute('name', $fileCoverage->path);

        $statements = 0;
        $covered = 0;
        $conditionals = 0;
        $coveredConditionals = 0;

        // Only decision points count as conditionals, so the file metrics equal
        // the sum of truecount/falsecount over the cond lines written below.
        $lineBranches = BranchCoverageAggregator::buildLineBranchMap($fileCoverage);
        foreach ($lineBranches as [$branchTotal, $branchCovered]) {
            $conditionals += $branchTotal;
            $coveredConditionals += $branchCovered;
        }

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

            if (isset($lineBranches[$lineNumber])) {
                [$branchTotal, $branchCovered] = $lineBranches[$lineNumber];

                // A branch may have more than two outgoing edges (`match` arms), so
                // truecount/falsecount carry covered/uncovered edge counts, not a literal pair.
                $xml->writeAttribute('type', 'cond');
                $xml->writeAttribute('truecount', (string) $branchCovered);
                $xml->writeAttribute('falsecount', (string) ($branchTotal - $branchCovered));
            } else {
                $xml->writeAttribute('type', 'stmt');
                $xml->writeAttribute('count', (string) $count);
            }

            $xml->endElement();
        }

        $this->writeMetrics($xml, [
            'statements' => $statements,
            'coveredstatements' => $covered,
            'conditionals' => $conditionals,
            'coveredconditionals' => $coveredConditionals,
            'elements' => $statements + $conditionals,
            'coveredelements' => $covered + $coveredConditionals,
        ]);

        $xml->endElement(); // file

        return [$statements, $covered, $conditionals, $coveredConditionals];
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
