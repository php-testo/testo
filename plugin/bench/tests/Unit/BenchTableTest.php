<?php

declare(strict_types=1);

namespace Tests\Bench\Unit;

use Testo\Assert;
use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\CaseResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\Line;
use Testo\Bench\Dto\Snap;
use Testo\Bench\Dto\ValueRel;
use Testo\Bench\Internal\Renderer;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(Renderer::class)]
final class BenchTableTest
{
    public function rowsFollowDeclarationOrderKeepingCurrentFirst(): void
    {
        $out = Renderer::table(self::result());

        Assert::true(\strpos($out, 'slow') < \strpos($out, 'fast'));
    }

    public function callsAreAttributedPerCaseNotByName(): void
    {
        $out = Renderer::table(self::result());
        $lines = \explode("\n", $out);

        $fastRow = self::rowContaining($lines, 'fast');
        $slowRow = self::rowContaining($lines, 'slow');

        Assert::string($fastRow)->contains('200');
        Assert::string($slowRow)->contains('100');
    }

    /**
     * @param list<string> $lines
     */
    private static function rowContaining(array $lines, string $needle): string
    {
        foreach ($lines as $line) {
            if (\str_contains($line, $needle)) {
                return $line;
            }
        }

        return '';
    }

    private static function result(): BenchResult
    {
        $slowCase = new CaseSet('slow', [new Snap(100, 0, 200.0), new Snap(100, 0, 200.0)]);
        $fastCase = new CaseSet('fast', [new Snap(200, 0, 100.0), new Snap(200, 0, 100.0)]);

        $slowLine = self::line(place: 2, name: 'slow', time: 2.0);
        $fastLine = self::line(place: 1, name: 'fast', time: 1.0);

        # Lines are declared slow-first; their keys index into $cases.
        return new BenchResult(
            cases: [$slowCase, $fastCase],
            results: [],
            lines: [$slowLine, $fastLine],
        );
    }

    private static function line(int $place, string $name, float $time): Line
    {
        $value = new ValueRel($time, 0.0);

        return new Line(
            place: $place,
            name: $name,
            avg: $value,
            med: $value,
            rstdev: 0.0,
            favg: $value,
            frstdev: 0.0,
            rejected: 0,
            reports: [],
        );
    }
}
