<?php

declare(strict_types=1);

namespace Testo\Codecov\Result;

/**
 * Aggregated code coverage data across multiple files.
 */
final readonly class CoverageResult
{
    public function __construct(
        /** @var array<non-empty-string, FileCoverage> File path => coverage data. */
        public array $files = [],
    ) {}

    /**
     * Creates a CoverageResult from raw driver data.
     *
     * Automatically detects the format:
     * - Line-only: `array<string, array<int, int>>`
     * - Branch/Path: `array<string, array{lines: array<int, int>, functions: array}>`
     *
     * File-level filtering is the driver's responsibility.
     */
    public static function fromRawData(array $rawData): self
    {
        $files = [];

        foreach ($rawData as $filePath => $fileData) {
            // Detect format: branch/path data has a 'lines' key
            $hasFunction = \is_array($fileData) && \array_key_exists('lines', $fileData);

            $rawLines = $hasFunction ? $fileData['lines'] : $fileData;

            $lineStatuses = [];
            foreach ($rawLines as $lineNumber => $status) {
                $lineStatus = LineStatus::tryFrom($status);
                $lineStatus !== null and $lineStatuses[$lineNumber] = $lineStatus;
            }

            $functions = [];
            if ($hasFunction && isset($fileData['functions'])) {
                $functions = self::parseFunctions($fileData['functions']);
            }

            ($lineStatuses !== [] || $functions !== [])
                and $files[$filePath] = new FileCoverage($filePath, $lineStatuses, $functions);
        }

        return new self($files);
    }

    /**
     * Merges another result into this one.
     */
    public function merge(self $other): self
    {
        $merged = $this->files;

        foreach ($other->files as $path => $fileCoverage) {
            $merged[$path] = isset($merged[$path])
                ? $merged[$path]->merge($fileCoverage)
                : $fileCoverage;
        }

        return new self($merged);
    }

    /**
     * @return array<non-empty-string, FunctionCoverage>
     */
    private static function parseFunctions(array $rawFunctions): array
    {
        $functions = [];

        foreach ($rawFunctions as $name => $data) {
            $branches = [];
            foreach ($data['branches'] ?? [] as $opStart => $branchData) {
                $branches[$opStart] = new BranchCoverage(
                    opStart: $branchData['op_start'],
                    opEnd: $branchData['op_end'],
                    lineStart: $branchData['line_start'],
                    lineEnd: $branchData['line_end'],
                    hit: $branchData['hit'] > 0,
                    out: $branchData['out'],
                    outHit: \array_map(static fn(int $h): bool => $h > 0, $branchData['out_hit']),
                );
            }

            $paths = [];
            foreach ($data['paths'] ?? [] as $pathData) {
                $paths[] = new PathCoverage(
                    path: $pathData['path'],
                    hit: $pathData['hit'] > 0,
                );
            }

            $functions[$name] = new FunctionCoverage($name, $branches, $paths);
        }

        return $functions;
    }
}
