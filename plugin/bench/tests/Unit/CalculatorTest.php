<?php

declare(strict_types=1);

namespace Tests\Bench\Unit;

use Testo\Assert;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\Snap;
use Testo\Bench\Internal\Calculator;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(Calculator::class)]
final class CalculatorTest
{
    public function allZeroMeasurementsDoNotDivideByZero(): void
    {
        $set = self::caseSet(\array_fill(0, 10, 0.0));

        $result = Calculator::calculate($set);

        Assert::same($result->frstdev, 0.0);
        Assert::same($result->rstdev, 0.0);
    }

    /**
     * @param list<float> $perCallUs Per-call time of each iteration, in microseconds.
     */
    private static function caseSet(array $perCallUs): CaseSet
    {
        return new CaseSet('probe', \array_map(
            static fn(float $us): Snap => new Snap(calls: 20, memory: 0, time: $us * 20),
            $perCallUs,
        ));
    }
}
