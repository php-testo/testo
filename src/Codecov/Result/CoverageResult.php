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

        /**
         * Project source root, used by reports that emit relative paths.
         * Stamped by {@see \Testo\Codecov\Internal\CoverageCollector} from
         * `ApplicationConfig::$src` before reports run. `null` until stamped.
         */
        public ?string $sourceRoot = null,
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

            $lineCoverages = [];
            foreach ($rawLines as $lineNumber => $status) {
                $lineStatus = LineStatus::tryFrom($status);
                $lineStatus !== null and $lineCoverages[$lineNumber] = new LineCoverage($lineNumber, $lineStatus);
            }

            $functions = [];
            if ($hasFunction && isset($fileData['functions'])) {
                $functions = self::parseFunctions($fileData['functions']);
            }

            ($lineCoverages !== [] || $functions !== [])
                and $files[$filePath] = new FileCoverage($filePath, $lineCoverages, $functions);
        }

        return new self($files);
    }

    /**
     * Returns a copy with the given test method stamped on every executed line of every file.
     *
     * @param non-empty-string $method
     */
    public function withTestMethod(string $method): self
    {
        $stamped = [];
        foreach ($this->files as $path => $fileCoverage) {
            $stamped[$path] = $fileCoverage->withTestMethod($method);
        }

        return new self($stamped, $this->sourceRoot);
    }

    /**
     * Returns a copy with the given source root.
     */
    public function withSourceRoot(string $sourceRoot): self
    {
        return new self($this->files, $sourceRoot);
    }

    /**
     * Merges another result into this one.
     *
     * If both sides carry a {@see $sourceRoot}, the receiver wins. Per-test results
     * coming from the interceptor never carry a source root — it's stamped once at
     * the end by {@see \Testo\Codecov\Internal\CoverageCollector}.
     */
    public function merge(self $other): self
    {
        $merged = $this->files;

        foreach ($other->files as $path => $fileCoverage) {
            $merged[$path] = isset($merged[$path])
                ? $merged[$path]->merge($fileCoverage)
                : $fileCoverage;
        }

        return new self($merged, $this->sourceRoot ?? $other->sourceRoot);
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
