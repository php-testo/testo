<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Dto;

use Testo\Assert;
use Testo\Codecov\Dto\PathCoverage;
use Testo\Test;

#[Test]
final class PathCoverageTest
{
    public function mergeHitWins(): void
    {
        $a = new PathCoverage([0, 4], hit: false);
        $b = new PathCoverage([0, 4], hit: true);

        $merged = $a->merge($b);

        Assert::true($merged->hit);
        Assert::same($merged->path, [0, 4]);
    }

    public function mergeBothNotHit(): void
    {
        $a = new PathCoverage([0, 7], hit: false);
        $b = new PathCoverage([0, 7], hit: false);

        Assert::false($a->merge($b)->hit);
    }

    public function mergeBothHit(): void
    {
        $a = new PathCoverage([0, 4, 10], hit: true);
        $b = new PathCoverage([0, 4, 10], hit: true);

        Assert::true($a->merge($b)->hit);
    }
}
