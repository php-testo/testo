<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

use Testo\Application\Config\FinderConfig;
use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Internal\CoverageDriver;
use Testo\Codecov\Result\CoverageResult;

/**
 * Test spy that tracks driver method calls.
 */
final class SpyDriver implements CoverageDriver
{
    public int $startCount = 0;
    public int $collectCount = 0;

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
        $this->startCount++;
    }

    #[\Override]
    public function collect(): CoverageResult
    {
        $this->collectCount++;
        return new CoverageResult();
    }

    #[\Override]
    public function clear(): void {}
}
