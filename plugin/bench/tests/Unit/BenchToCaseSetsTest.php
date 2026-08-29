<?php

declare(strict_types=1);

namespace Tests\Bench\Unit;

use Testo\Assert;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\IterationSet;
use Testo\Bench\Dto\Snap;
use Testo\Bench\Internal\BenchHandler;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(BenchHandler::class)]
final class BenchToCaseSetsTest
{
    public function iterationCentricSnapsRegroupIntoCaseCentricSets(): void
    {
        $sets = self::toCaseSets(
            [
                new IterationSet(1, [self::snap(10.0), self::snap(20.0)]),
                new IterationSet(2, [self::snap(11.0), self::snap(21.0)]),
            ],
            ['current', 'alt'],
        );

        Assert::count($sets, 2);

        Assert::same($sets[0]->name, 'current');
        Assert::same([$sets[0]->iterations[0]->time, $sets[0]->iterations[1]->time], [10.0, 11.0]);

        Assert::same($sets[1]->name, 'alt');
        Assert::same([$sets[1]->iterations[0]->time, $sets[1]->iterations[1]->time], [20.0, 21.0]);
    }

    public function aMissingNameFallsBackToTheStringifiedIndex(): void
    {
        $sets = self::toCaseSets(
            [new IterationSet(1, [self::snap(10.0), self::snap(20.0)])],
            ['current'],
        );

        Assert::same($sets[0]->name, 'current');
        Assert::same($sets[1]->name, '1');
    }

    /**
     * @param list<IterationSet> $iterations
     * @param list<string> $names
     * @return list<CaseSet>
     */
    private static function toCaseSets(array $iterations, array $names): array
    {
        /** @var list<CaseSet> */
        return (new \ReflectionMethod(BenchHandler::class, 'toCaseSets'))
            ->invoke(null, $iterations, $names);
    }

    private static function snap(float $time): Snap
    {
        return new Snap(calls: 1, memory: 0, time: $time);
    }
}
