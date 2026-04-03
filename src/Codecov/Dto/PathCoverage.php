<?php

declare(strict_types=1);

namespace Testo\Codecov\Dto;

/**
 * A single execution path through a function.
 *
 * A path is an ordered sequence of branches (identified by their opcode start indices)
 * that were followed during execution.
 */
final readonly class PathCoverage
{
    public function __construct(
        /** @var list<int<0, max>> Sequence of branch opcode start indices forming this path. */
        public array $path,

        /** Whether this path was followed during execution. */
        public bool $hit,
    ) {}

    public function merge(self $other): self
    {
        return new self($this->path, $this->hit || $other->hit);
    }
}
