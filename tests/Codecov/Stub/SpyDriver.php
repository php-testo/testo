<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

use Testo\Application\Config\FinderConfig;
use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Internal\CoverageDriver;
use Testo\Codecov\Result\CoverageResult;

/**
 * Test spy that tracks driver method calls and returns configurable results.
 */
final class SpyDriver implements CoverageDriver
{
    public int $startCount = 0;
    public int $collectCount = 0;

    /** @var list<FinderConfig> */
    public array $filterCalls = [];

    public function __construct(
        private CoverageResult $result = new CoverageResult(),
    ) {}

    #[\Override]
    public function withFilter(FinderConfig $filter): static
    {
        $clone = clone $this;
        $clone->filterCalls[] = $filter;
        return $clone;
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
        return $this->result;
    }

    #[\Override]
    public function clear(): void {}
}
