<?php

declare(strict_types=1);

namespace Testo\Codecov\Dto;

use Testo\Application\Config\FinderConfig;

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
     * Creates a CoverageResult from raw driver data, filtered by the given config.
     *
     * @param array<string, array<int, int>> $rawData Raw coverage data from the driver.
     */
    public static function fromRawData(array $rawData, FinderConfig $filter): self
    {
        $files = [];

        foreach ($rawData as $filePath => $lines) {
            if (!self::matchesFilter($filePath, $filter)) {
                continue;
            }

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

    private static function matchesFilter(string $filePath, FinderConfig $filter): bool
    {
        if ($filter->includes !== []) {
            $included = false;
            foreach ($filter->includes as $path) {
                if (\str_starts_with($filePath, (string) $path)) {
                    $included = true;
                    break;
                }
            }

            if (!$included) {
                return false;
            }
        }

        foreach ($filter->excludes as $path) {
            if (\str_starts_with($filePath, (string) $path)) {
                return false;
            }
        }

        return true;
    }
}
