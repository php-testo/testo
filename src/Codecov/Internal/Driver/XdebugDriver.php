<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal\Driver;

use Testo\Application\Config\FinderConfig;
use Testo\Codecov\CoverageDriver;
use Testo\Codecov\CoverageLevel;
use Testo\Codecov\Dto\CoverageResult;

/**
 * XDebug-based coverage driver.
 *
 * Requires XDebug >= 3.0 with `coverage` mode enabled.
 *
 * @internal
 */
final readonly class XdebugDriver implements CoverageDriver
{
    use NormalizePath;
    /**
     * @param list<non-empty-string> $includes
     * @param list<non-empty-string> $excludes
     */
    public function __construct(
        private array $includes = [],
        private array $excludes = [],
        private CoverageLevel $level = CoverageLevel::Line,
    ) {
        # Tag files not yet loaded by the autoloader for engine-level filtering.
        # Files already compiled are filtered in collect() as a fallback.
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
            \array_map(self::normalizePath(...), $filter->includes),
            \array_map(self::normalizePath(...), $filter->excludes),
            $this->level,
        );
    }

    #[\Override]
    public function withLevel(CoverageLevel $level): static
    {
        return new self($this->includes, $this->excludes, $level);
    }

    #[\Override]
    public function start(): void
    {
        $flags = \XDEBUG_CC_UNUSED | \XDEBUG_CC_DEAD_CODE;

        $this->level !== CoverageLevel::Line and $flags |= \XDEBUG_CC_BRANCH_CHECK;

        \xdebug_start_code_coverage($flags);
    }

    #[\Override]
    public function collect(): CoverageResult
    {
        $data = \xdebug_get_code_coverage();
        \xdebug_stop_code_coverage();

        if ($this->includes !== [] || $this->excludes !== []) {
            foreach ($data as $filePath => $_) {
                if ($this->includes !== [] && !$this->matchesAny($filePath, $this->includes)) {
                    unset($data[$filePath]);
                    continue;
                }

                if ($this->matchesAny($filePath, $this->excludes)) {
                    unset($data[$filePath]);
                }
            }
        }

        return CoverageResult::fromRawData($data);
    }

    #[\Override]
    public function clear(): void
    {
        # XDebug clears data on \\xdebug_stop_code_coverage() by default.
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
