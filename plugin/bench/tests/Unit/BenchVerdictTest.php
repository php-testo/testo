<?php

declare(strict_types=1);

namespace Tests\Bench\Unit;

use Testo\Assert;
use Testo\Bench\Dto\CaseResult;
use Testo\Bench\Internal\BenchHandler;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(BenchHandler::class)]
final class BenchVerdictTest
{
    public function currentBeingTheFastestPasses(): void
    {
        $record = BenchHandler::benchmarkVerdict(self::results(1.0, 2.0), ['current', 'alt'], 0.02);

        Assert::true($record->isSuccess());
    }

    public function currentWithinToleranceOfTheFastestPasses(): void
    {
        $record = BenchHandler::benchmarkVerdict(self::results(1.02, 1.0), ['current', 'alt'], 0.05);

        Assert::true($record->isSuccess());
    }

    public function currentSlowerBeyondToleranceFailsAndNamesTheWinner(): void
    {
        $record = BenchHandler::benchmarkVerdict(self::results(2.0, 1.0), ['current', 'alt'], 0.02);

        Assert::false($record->isSuccess());
        Assert::string((string) $record)->contains("'alt' is 100.0% faster");
    }

    public function infiniteTolerancePassesEvenWhenCurrentIsSlowest(): void
    {
        $record = BenchHandler::benchmarkVerdict(self::results(100.0, 1.0), ['current', 'alt'], \INF);

        Assert::true($record->isSuccess());
    }

    /**
     * @return list<CaseResult> Case results carrying only the filtered mean the verdict reads.
     */
    private static function results(float ...$favg): array
    {
        return \array_map(
            static fn(float $favg): CaseResult => new CaseResult(
                mean: $favg,
                med: $favg,
                rstdev: 0.0,
                rejected: 0,
                favg: $favg,
                frstdev: 0.0,
            ),
            $favg,
        );
    }
}
