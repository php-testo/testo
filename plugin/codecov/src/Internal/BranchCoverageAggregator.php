<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

use Testo\Codecov\Result\FileCoverage;

/**
 * Reduces {@see FileCoverage::$functions} branch data into the shapes report writers need:
 * a file-level edge count and a per-line map of branch decision points.
 *
 * @internal
 */
final class BranchCoverageAggregator
{
    private function __construct() {}

    /**
     * Counts outgoing edges of every branch in the file, including single-edge linear jumps.
     *
     * @return array{int<0, max>, int<0, max>} [total edges, covered edges]
     */
    public static function countBranches(FileCoverage $fileCoverage): array
    {
        $total = 0;
        $covered = 0;

        foreach ($fileCoverage->functions as $function) {
            foreach ($function->branches as $branch) {
                $total += \count($branch->outHit);
                $covered += \count(\array_filter($branch->outHit));
            }
        }

        return [$total, $covered];
    }

    /**
     * Maps line number to [total edges, covered edges] for branch decision points only:
     * a branch with a single outgoing edge is a linear jump, not a decision.
     * Branches starting on the same line are summed.
     *
     * @return array<int, array{int<0, max>, int<0, max>}>
     */
    public static function buildLineBranchMap(FileCoverage $fileCoverage): array
    {
        $map = [];

        foreach ($fileCoverage->functions as $function) {
            foreach ($function->branches as $branch) {
                if (\count($branch->out) < 2) {
                    continue;
                }

                $line = $branch->lineStart;
                $total = \count($branch->outHit);
                $covered = \count(\array_filter($branch->outHit));

                $map[$line] ??= [0, 0];

                $map[$line][0] += $total;
                $map[$line][1] += $covered;
            }
        }

        return $map;
    }
}
