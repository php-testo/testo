<?php

declare(strict_types=1);

namespace Testo\Output\Rendering\Diff;

/**
 * Computes a line-by-line diff between two multi-line strings.
 *
 * Implementations split both sides on "\n" and return an ordered edit script: every input line
 * appears exactly once as a {@see DiffLine}, tagged as kept ({@see DiffOp::Context}), removed from
 * the expected side ({@see DiffOp::Remove}), or added on the actual side ({@see DiffOp::Add}).
 *
 * @internal
 */
interface Differ
{
    /**
     * @return list<DiffLine>
     */
    public function diff(string $expected, string $actual): array;
}
