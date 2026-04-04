<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Dto;

use Testo\Assert;
use Testo\Codecov\Dto\BranchCoverage;
use Testo\Codecov\Dto\FunctionCoverage;
use Testo\Codecov\Dto\PathCoverage;
use Testo\Test;

#[Test]
final class FunctionCoverageTest
{
    public function mergeBranchesAndPaths(): void
    {
        $a = new FunctionCoverage('Foo->bar', [
            0 => new BranchCoverage(0, 3, 5, 6, hit: true, out: [4, 7], outHit: [true, false]),
        ], [
            new PathCoverage([0, 4], hit: true),
            new PathCoverage([0, 7], hit: false),
        ]);

        $b = new FunctionCoverage('Foo->bar', [
            0 => new BranchCoverage(0, 3, 5, 6, hit: false, out: [4, 7], outHit: [false, true]),
        ], [
            new PathCoverage([0, 4], hit: false),
            new PathCoverage([0, 7], hit: true),
        ]);

        // Act
        $merged = $a->merge($b);

        // Assert branches
        Assert::true($merged->branches[0]->hit);
        Assert::same($merged->branches[0]->outHit, [true, true]);

        // Assert paths
        Assert::true($merged->paths[0]->hit);
        Assert::true($merged->paths[1]->hit);
    }

    public function mergeAddsNewBranches(): void
    {
        $a = new FunctionCoverage('Foo->bar', [
            0 => new BranchCoverage(0, 3, 5, 6, hit: true, out: [4], outHit: [true]),
        ], []);

        $b = new FunctionCoverage('Foo->bar', [
            0 => new BranchCoverage(0, 3, 5, 6, hit: false, out: [4], outHit: [false]),
            5 => new BranchCoverage(5, 8, 8, 10, hit: true, out: [9], outHit: [true]),
        ], []);

        $merged = $a->merge($b);

        Assert::count($merged->branches, 2);
        Assert::true($merged->branches[0]->hit);
        Assert::true($merged->branches[5]->hit);
    }
}
