<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

use Testo\Application\Config\FinderConfig;
use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Internal\CoverageDriver;
use Testo\Codecov\Result\CoverageResult;

/**
 * Driver stub that models a real coverage extension's **process-global** window.
 *
 * There is one window for the whole process, not one per caller:
 * - {@see start()} opens it and keeps whatever is already recorded — a second `start()` is not a reset;
 * - {@see touch()} records a line only while the window is open;
 * - {@see collect()} returns everything recorded and closes the window, clearing the data.
 *
 * That is the shape both XDebug and PCOV expose, and it is what makes a per-test window unable to
 * survive an interleave on its own: whoever collects first takes every line recorded since any
 * `start()`, and leaves the others with nothing.
 */
final class WindowDriver implements CoverageDriver
{
    private bool $open = false;

    /** @var array<non-empty-string, array<int, int>> */
    private array $recorded = [];

    public function open(): bool
    {
        return $this->open;
    }

    /**
     * Pretend the given line just executed.
     *
     * @param non-empty-string $file
     */
    public function touch(string $file, int $line): void
    {
        $this->open and $this->recorded[$file][$line] = 1;
    }

    #[\Override]
    public function withFilter(FinderConfig $filter): static
    {
        return $this;
    }

    #[\Override]
    public function withLevel(CoverageLevel $level): static
    {
        return $this;
    }

    #[\Override]
    public function start(): void
    {
        $this->open = true;
    }

    #[\Override]
    public function collect(): CoverageResult
    {
        $recorded = $this->recorded;

        $this->open = false;
        $this->recorded = [];

        return CoverageResult::fromRawData($recorded);
    }

    #[\Override]
    public function clear(): void
    {
        $this->recorded = [];
    }
}
