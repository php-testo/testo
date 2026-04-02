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
    ) {}

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
        $this->includes !== [] and \xdebug_set_filter(
            \XDEBUG_FILTER_CODE_COVERAGE,
            \XDEBUG_PATH_INCLUDE,
            $this->includes,
        );

        \xdebug_start_code_coverage(\XDEBUG_CC_UNUSED | \XDEBUG_CC_DEAD_CODE);
    }

    #[\Override]
    public function collect(): array
    {
        $data = \xdebug_get_code_coverage();
        \xdebug_stop_code_coverage();

        foreach ($this->excludes as $exclude) {
            foreach ($data as $filePath => $_) {
                \str_starts_with($filePath, $exclude) and $data[$filePath] = null;
            }
        }

        return \array_filter($data);
    }

    #[\Override]
    public function clear(): void
    {
        // XDebug clears data on xdebug_stop_code_coverage() by default.
    }
}
