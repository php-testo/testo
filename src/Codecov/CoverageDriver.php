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
     * When set, only files matching the filter's include/exclude paths are tracked.
     */
    public function withFilter(FinderConfig $filter): static;

    /**
     * Starts code coverage collection.
     */
    public function start(): void;

    /**
     * Stops collection and returns the raw coverage data.
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
