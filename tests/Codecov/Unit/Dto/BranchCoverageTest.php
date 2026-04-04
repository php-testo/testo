<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Dto;

use Testo\Assert;
use Testo\Codecov\Dto\BranchCoverage;
use Testo\Test;

#[Test]
final class BranchCoverageTest
{
    public function mergeHitWins(): void
    {
        $a = new BranchCoverage(0, 3, 5, 6, hit: false, out: [4, 7], outHit: [false, false]);
        $b = new BranchCoverage(0, 3, 5, 6, hit: true, out: [4, 7], outHit: [true, false]);

        $merged = $a->merge($b);

        Assert::true($merged->hit);
        Assert::same($merged->outHit, [true, false]);
    }

    public function mergeOutHitCombines(): void
    {
        $a = new BranchCoverage(0, 3, 5, 6, hit: true, out: [4, 7], outHit: [true, false]);
        $b = new BranchCoverage(0, 3, 5, 6, hit: false, out: [4, 7], outHit: [false, true]);

        $merged = $a->merge($b);

        Assert::same($merged->outHit, [true, true]);
    }

    public function mergePreservesStructuralFields(): void
    {
        $a = new BranchCoverage(0, 5, 10, 12, hit: false, out: [6, 9], outHit: [false, false]);
        $b = new BranchCoverage(0, 5, 10, 12, hit: false, out: [6, 9], outHit: [false, false]);

        $merged = $a->merge($b);

        Assert::same($merged->opStart, 0);
        Assert::same($merged->opEnd, 5);
        Assert::same($merged->lineStart, 10);
        Assert::same($merged->lineEnd, 12);
        Assert::same($merged->out, [6, 9]);
    }
}
