<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal\Driver;

use Testo\Application\Config\FinderConfig;
use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Internal\CoverageDriver;
use Testo\Inline\TestInline;

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
    private function __construct(
        private array $includes = [],
        private array $excludes = [],
        private CoverageLevel $level = CoverageLevel::Line,
    ) {}

    /**
     * Creates a new XDebug driver and sets the global engine-level filter.
     *
     * `xdebug_set_filter()` tags files at first include/require and cannot re-tag
     * already loaded files. It must be called once with the broadest scope (e.g. `src/`).
     * Per-test narrowing is handled in {@see collect()} at PHP level.
     */
    public static function create(FinderConfig $src): self
    {
        $includes = \array_map(self::normalizePath(...), $src->includes);
        $excludes = \array_map(self::normalizePath(...), $src->excludes);

        $includes !== [] and \xdebug_set_filter(
            \XDEBUG_FILTER_CODE_COVERAGE,
            \XDEBUG_PATH_INCLUDE,
            $includes,
        );

        return new self($includes, $excludes);
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
                if ($this->includes !== [] && !self::matchesAny($filePath, $this->includes)) {
                    unset($data[$filePath]);
                    continue;
                }

                if (self::matchesAny($filePath, $this->excludes)) {
                    unset($data[$filePath]);
                }
            }
        }

        return CoverageResult::fromRawData($data);
    }

    #[\Override]
    public function clear(): void
    {
        # XDebug clears data on \xdebug_stop_code_coverage() by default.
    }

    /**
     * @param list<non-empty-string> $prefixes
     */
    #[TestInline(['/src/Foo.php', ['/src/']], result: true)]
    #[TestInline(['/src/Bar/Baz.php', ['/src/', '/lib/']], result: true)]
    #[TestInline(['/vendor/Foo.php', ['/src/']], result: false)]
    #[TestInline(['/src/Foo.php', []], result: false)]
    #[TestInline(['', ['/src/']], result: false)]
    #[TestInline(['/src/', ['/src/']], result: true)]
    #[TestInline(['C:\\project\\src\\Foo.php', ['C:\\project\\src\\']], result: true)]
    private static function matchesAny(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (\str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
