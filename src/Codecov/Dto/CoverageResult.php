<?php

declare(strict_types=1);

namespace Testo\Codecov\Dto;

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
     * File-level filtering is the driver's responsibility.
     *
     * @param array<string, array<int, int>> $rawData Raw coverage data from the driver.
     */
    public static function fromRawData(array $rawData): self
    {
        $files = [];

        foreach ($rawData as $filePath => $lines) {
            $lineStatuses = [];
            foreach ($lines as $lineNumber => $status) {
                $lineStatus = LineStatus::tryFrom($status);
                $lineStatus !== null and $lineStatuses[$lineNumber] = $lineStatus;
            }

            $lineStatuses !== [] and $files[$filePath] = new FileCoverage($filePath, $lineStatuses);
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
}
