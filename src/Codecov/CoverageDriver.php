<?php

declare(strict_types=1);

namespace Testo\Codecov;

use Testo\Application\Config\FinderConfig;

/**
 * Interface for code coverage collection engines.
 *
 * @api
 */
interface CoverageDriver
{
    /**
     * Returns a new driver instance with the given path filter.
     *
     * The driver is responsible for file-level filtering: only files matching
     * the filter's include paths and not matching exclude paths are returned
     * by {@see collect()}.
     */
    public function withFilter(FinderConfig $filter): static;

    /**
     * Starts code coverage collection.
     */
    public function start(): void;

    /**
     * Stops collection and returns the raw coverage data.
     *
     * The returned data must already be filtered by the configured {@see FinderConfig}
     * (set via {@see withFilter()}). Callers should not need to apply file-level filtering.
     *
     * @return array<string, array<int, int>> File path => [line number => status].
     *         Status values: 1 = executed, -1 = not executed, -2 = not executable.
     */
    public function collect(): array;

    /**
     * Clears any accumulated coverage data.
     */
    public function clear(): void;
}
