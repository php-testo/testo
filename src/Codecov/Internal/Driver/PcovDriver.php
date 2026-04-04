<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal\Driver;

use Testo\Application\Config\FinderConfig;
use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Internal\CoverageDriver;

/**
 * PCOV-based coverage driver.
 *
 * @internal
 */
final readonly class PcovDriver implements CoverageDriver
{
    use NormalizePath;

    /**
     * @param list<non-empty-string> $includes
     * @param list<non-empty-string> $excludes
     */
    private function __construct(
        private array $includes = [],
        private array $excludes = [],
    ) {}

    /**
     * Creates a new PCOV driver.
     */
    public static function create(FinderConfig $src): self
    {
        return new self(
            \array_map(self::normalizePath(...), $src->includes),
            \array_map(self::normalizePath(...), $src->excludes),
        );
    }

    #[\Override]
    public function withFilter(FinderConfig $filter): static
    {
        return new self(
            \array_map(self::normalizePath(...), $filter->includes),
            \array_map(self::normalizePath(...), $filter->excludes),
        );
    }

    /**
     * PCOV only supports line coverage. Higher levels silently fall back to Line.
     */
    #[\Override]
    public function withLevel(CoverageLevel $level): static
    {
        return $this;
    }

    #[\Override]
    public function start(): void
    {
        \pcov\start();
    }

    #[\Override]
    public function collect(): CoverageResult
    {
        \pcov\stop();

        if ($this->includes === []) {
            $data = \pcov\collect();
        } else {
            $waiting = \pcov\waiting();
            $filtered = \array_filter($waiting, $this->matchesFilter(...));
            $data = $filtered !== []
                ? \pcov\collect(\pcov\inclusive, \array_values($filtered))
                : [];
        }

        \pcov\clear();

        return CoverageResult::fromRawData($data);
    }

    #[\Override]
    public function clear(): void
    {
        \pcov\clear();
    }

    private function matchesFilter(string $file): bool
    {
        $included = false;
        foreach ($this->includes as $path) {
            if (\str_starts_with($file, $path)) {
                $included = true;
                break;
            }
        }

        if (!$included) {
            return false;
        }

        foreach ($this->excludes as $path) {
            if (\str_starts_with($file, $path)) {
                return false;
            }
        }

        return true;
    }
}
