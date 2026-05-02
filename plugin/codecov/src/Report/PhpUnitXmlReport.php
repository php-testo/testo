<?php

declare(strict_types=1);

namespace Testo\Codecov\Report;

use Internal\Path;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\FileCoverage;
use Testo\Codecov\Result\LineStatus;

/**
 * Generates a PHPUnit-style coverage XML report directory.
 *
 * Output layout:
 * - `<outputDir>/index.xml` — overview with one `<file href="…">` per source file.
 * - `<outputDir>/<relative>/<basename>.xml` — per-source-file coverage with `<covered by="…">`.
 *
 * The format matches the PHPUnit `--coverage-xml` directory layout consumed by Infection's
 * `Infection\TestFramework\Coverage\XmlReport\IndexXmlCoverageParser` / `XmlCoverageParser`.
 *
 * @api
 */
final readonly class PhpUnitXmlReport implements CoverageReport
{
    private const XMLNS = 'https://schema.phpunit.de/coverage/1.0';

    public function __construct(
        /** @var non-empty-string Output directory for the report. Created if missing. */
        private string $outputDir,
    ) {}

    #[\Override]
    public function generate(CoverageResult $result): void
    {
        $sourceRoot = (string) Path::create($result->sourceRoot ?? (string) \getcwd());

        \is_dir($this->outputDir) or \mkdir($this->outputDir, 0o755, true);

        $entries = [];
        $totalStmts = 0;
        $totalCovered = 0;

        foreach ($result->files as $absPath => $file) {
            [$stmts, $covered] = self::countLines($file);
            if ($stmts === 0) {
                continue;
            }

            $totalStmts += $stmts;
            $totalCovered += $covered;

            $entries[] = [
                'fileCoverage' => $file,
                'href' => self::computeHref($absPath, $sourceRoot),
                'name' => \basename(\str_replace('\\', '/', $absPath)),
                'path' => self::computeRelativeDir($absPath, $sourceRoot),
                'stmts' => $stmts,
                'covered' => $covered,
            ];
        }

        $this->writeIndex($entries, $sourceRoot, $totalStmts, $totalCovered);

        foreach ($entries as $entry) {
            $this->writeFile($entry);
        }
    }

    /**
     * @param list<array{fileCoverage: FileCoverage, href: string, name: string, path: string, stmts: int<0, max>, covered: int<0, max>}> $entries
     */
    private function writeIndex(array $entries, string $sourceRoot, int $totalStmts, int $totalCovered): void
    {
        $xml = self::newXmlWriter();
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('phpunit');
        $xml->writeAttribute('xmlns', self::XMLNS);

        $xml->startElement('project');
        $xml->writeAttribute('source', $sourceRoot);
        $xml->writeAttribute('timestamp', (string) \time());

        $xml->startElement('directory');
        $xml->writeAttribute('name', $sourceRoot === '' ? '/' : $sourceRoot);
        self::writeLineTotals($xml, $totalCovered, $totalStmts);

        foreach ($entries as $entry) {
            $xml->startElement('file');
            $xml->writeAttribute('name', $entry['name']);
            $xml->writeAttribute('href', $entry['href']);
            self::writeLineTotals($xml, $entry['covered'], $entry['stmts']);
            $xml->endElement(); // file
        }

        $xml->endElement(); // directory
        $xml->endElement(); // project
        $xml->endElement(); // phpunit
        $xml->endDocument();

        \file_put_contents($this->outputDir . '/index.xml', $xml->outputMemory());
    }

    /**
     * @param array{fileCoverage: FileCoverage, href: string, name: string, path: string, stmts: int<0, max>, covered: int<0, max>} $entry
     */
    private function writeFile(array $entry): void
    {
        $fileCoverage = $entry['fileCoverage'];
        $outputPath = $this->outputDir . '/' . $entry['href'];

        $dir = \dirname($outputPath);
        \is_dir($dir) or \mkdir($dir, 0o755, true);

        $xml = self::newXmlWriter();
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('phpunit');
        $xml->writeAttribute('xmlns', self::XMLNS);

        $xml->startElement('file');
        $xml->writeAttribute('name', $entry['name']);
        $entry['path'] === '' or $xml->writeAttribute('path', $entry['path']);

        self::writeLineTotals($xml, $entry['covered'], $entry['stmts']);

        $xml->startElement('coverage');

        $lines = $fileCoverage->lines;
        \ksort($lines);

        foreach ($lines as $lineNumber => $line) {
            if ($line->status !== LineStatus::Executed || $line->testMethods === []) {
                continue;
            }

            $xml->startElement('line');
            $xml->writeAttribute('nr', (string) $lineNumber);

            foreach ($line->testMethods as $method) {
                $xml->startElement('covered');
                $xml->writeAttribute('by', $method);
                $xml->endElement(); // covered
            }

            $xml->endElement(); // line
        }

        $xml->endElement(); // coverage
        $xml->endElement(); // file
        $xml->endElement(); // phpunit
        $xml->endDocument();

        \file_put_contents($outputPath, $xml->outputMemory());
    }

    private static function writeLineTotals(\XMLWriter $xml, int $executed, int $total): void
    {
        $percent = $total === 0 ? '0.00' : \sprintf('%.2f', 100 * $executed / $total);

        $xml->startElement('totals');
        $xml->startElement('lines');
        $xml->writeAttribute('total', (string) $total);
        $xml->writeAttribute('executed', (string) $executed);
        $xml->writeAttribute('percent', $percent);
        $xml->endElement(); // lines
        $xml->endElement(); // totals
    }

    /**
     * @return array{int<0, max>, int<0, max>} [statements, executed]
     */
    private static function countLines(FileCoverage $fileCoverage): array
    {
        $stmts = 0;
        $executed = 0;

        foreach ($fileCoverage->lines as $lineCoverage) {
            if (!$lineCoverage->status->isExecutable()) {
                continue;
            }

            $stmts++;
            $lineCoverage->status === LineStatus::Executed and $executed++;
        }

        return [$stmts, $executed];
    }

    private static function computeHref(string $absPath, string $sourceRoot): string
    {
        $normalized = \str_replace('\\', '/', $absPath);

        if ($sourceRoot !== '' && \str_starts_with($normalized, $sourceRoot . '/')) {
            return \substr($normalized, \strlen($sourceRoot) + 1) . '.xml';
        }

        // File outside sourceRoot — flatten to a slug under the output dir.
        return \ltrim(\str_replace([':', '/'], '_', $normalized), '_') . '.xml';
    }

    private static function computeRelativeDir(string $absPath, string $sourceRoot): string
    {
        $normalized = \str_replace('\\', '/', $absPath);

        if ($sourceRoot === '' || !\str_starts_with($normalized, $sourceRoot . '/')) {
            return '';
        }

        $relative = \substr($normalized, \strlen($sourceRoot) + 1);
        $dir = \dirname($relative);

        return $dir === '.' ? '' : $dir;
    }

    private static function newXmlWriter(): \XMLWriter
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        return $xml;
    }
}
