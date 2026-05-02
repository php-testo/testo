<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

use Testo\Application\Config\FinderConfig;
use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Result\CoverageResult;

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
     * Returns a new driver instance with the given coverage level.
     *
     * If the driver does not support the requested level, it should fall back
     * to the highest supported level (e.g. PCOV supports only {@see CoverageLevel::Line}).
     */
    public function withLevel(CoverageLevel $level): static;

    /**
     * Starts code coverage collection.
     */
    public function start(): void;

    /**
     * Stops collection and returns coverage data as DTOs.
     *
     * The returned data is already filtered by the configured {@see FinderConfig}
     * and parsed according to the configured {@see CoverageLevel}.
     */
    public function collect(): CoverageResult;

    /**
     * Clears any accumulated coverage data.
     */
    public function clear(): void;
}
