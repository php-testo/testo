<?php

declare(strict_types=1);

namespace Tests\Bench\Unit;

use Testo\Assert;
use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\Line;
use Testo\Bench\Dto\Report;
use Testo\Bench\Dto\ValueRel;
use Testo\Bench\Internal\Renderer;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(Renderer::class)]
final class BenchRecommendationsTest
{
    public function causesThatShareAdviceCollapseUnderOneHeading(): void
    {
        $out = Renderer::recommendations(self::resultWith(
            new Report\InsufficientIterTime(15.0, 5.0),
            new Report\HighVarianceLowIterTime(15.0, 5.0),
        ));

        Assert::same(\substr_count($out, 'Increase calls per iteration.'), 1);
        Assert::string($out)->contains("  Increase calls per iteration.");
        Assert::string($out)->contains("      ⚠ Insufficient iter time");
        Assert::string($out)->contains("      ⚠ High variance, low iter time");
    }

    public function sharedAdviceGroupsCausesAcrossSeveritiesMostSevereFirst(): void
    {
        $out = Renderer::recommendations(self::resultWith(
            new Report\InsufficientIterTime(15.0, 5.0),
            new Report\UnreliableLowIterTime(25.0, 5.0),
        ));

        Assert::same(\substr_count($out, 'Increase calls per iteration.'), 1);
        Assert::true(
            \strpos($out, '✗ Unreliable, low iter time') < \strpos($out, '⚠ Insufficient iter time'),
        );
    }

    public function distinctAdviceStaysSeparate(): void
    {
        $out = Renderer::recommendations(self::resultWith(
            new Report\InsufficientIterTime(15.0, 5.0),
            new Report\HighVariance(15.0),
        ));

        Assert::same(\substr_count($out, 'Increase calls per iteration.'), 1);
        Assert::same(\substr_count($out, 'Increase iterations or isolate side effects.'), 1);
    }

    public function identicalCausesAppearOnce(): void
    {
        $out = Renderer::recommendations(self::resultWith(
            new Report\InsufficientIterTime(15.0, 5.0),
            new Report\InsufficientIterTime(20.0, 6.0),
        ));

        Assert::same(\substr_count($out, 'Insufficient iter time'), 1);
    }

    public function noReportsRenderNothing(): void
    {
        Assert::same(Renderer::recommendations(self::resultWith()), '');
    }

    private static function resultWith(Report ...$reports): BenchResult
    {
        $zero = new ValueRel(0.0, 0.0);
        $line = new Line(
            place: 1,
            name: 'current',
            avg: $zero,
            med: $zero,
            rstdev: 0.0,
            favg: $zero,
            frstdev: 0.0,
            rejected: 0,
            reports: $reports,
        );

        return new BenchResult(cases: [], results: [], lines: [$line]);
    }
}
