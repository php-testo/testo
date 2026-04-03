<?php

declare(strict_types=1);

namespace Testo\Codecov\Dto;

/**
 * A single branch within a function's control flow graph.
 */
final readonly class BranchCoverage
{
    public function __construct(
        /** @var int<0, max> Opcode index where this branch starts. */
        public int $opStart,

        /** @var int<0, max> Opcode index where this branch ends. */
        public int $opEnd,

        /** @var int<1, max> Source line where this branch starts. */
        public int $lineStart,

        /** @var int<1, max> Source line where this branch ends. */
        public int $lineEnd,

        /** Whether this branch was executed. */
        public bool $hit,

        /** @var list<int<0, max>> Outgoing branch opcode indices. */
        public array $out,

        /** @var list<bool> Whether each outgoing branch was taken. */
        public array $outHit,
    ) {}

    public function merge(self $other): self
    {
        return new self(
            $this->opStart,
            $this->opEnd,
            $this->lineStart,
            $this->lineEnd,
            $this->hit || $other->hit,
            $this->out,
            \array_map(
                static fn(bool $a, bool $b): bool => $a || $b,
                $this->outHit,
                $other->outHit,
            ),
        );
    }
}
