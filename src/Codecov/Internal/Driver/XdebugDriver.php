<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal\Driver;

use Testo\Application\Config\FinderConfig;
use Testo\Codecov\CoverageDriver;

/**
 * XDebug-based coverage driver.
 *
 * Requires XDebug >= 3.0 with `coverage` mode enabled.
 *
 * @internal
 */
final readonly class XdebugDriver implements CoverageDriver
{
    /**
     * @param list<non-empty-string> $includes
     * @param list<non-empty-string> $excludes
     */
    public function __construct(
        private array $includes = [],
        private array $excludes = [],
    ) {
        // Tag files not yet loaded by the autoloader for engine-level filtering.
        // Files already compiled are filtered in collect() as a fallback.
        $this->includes !== [] and \xdebug_set_filter(
            \XDEBUG_FILTER_CODE_COVERAGE,
            \XDEBUG_PATH_INCLUDE,
            $this->includes,
        );
    }

    #[\Override]
    public function withFilter(FinderConfig $filter): static
    {
        return new self(
            \array_map(\strval(...), $filter->includes),
            \array_map(\strval(...), $filter->excludes),
        );
    }

    #[\Override]
    public function start(): void
    {
        \xdebug_start_code_coverage(\XDEBUG_CC_UNUSED | \XDEBUG_CC_DEAD_CODE);
    }

    #[\Override]
    public function collect(): array
    {
        $data = \xdebug_get_code_coverage();
        \xdebug_stop_code_coverage();

        if ($this->includes === [] && $this->excludes === []) {
            return $data;
        }

        $filtered = [];
        foreach ($data as $filePath => $lines) {
            $normalized = \str_replace('\\', '/', $filePath);

            if ($this->includes !== [] && !$this->matchesAny($normalized, $this->includes)) {
                continue;
            }

            if ($this->matchesAny($normalized, $this->excludes)) {
                continue;
            }

            $filtered[$filePath] = $lines;
        }

        return $filtered;
    }

    #[\Override]
    public function clear(): void
    {
        // XDebug clears data on \xdebug_stop_code_coverage() by default.
    }

    /**
     * @param list<non-empty-string> $prefixes
     */
    private function matchesAny(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (\str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
