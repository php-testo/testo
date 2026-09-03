<?php

declare(strict_types=1);

namespace Tests\Bench\Unit;

use Testo\Assert;
use Testo\Bench\Dto\CaseResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\Report;
use Testo\Bench\Dto\Snap;
use Testo\Bench\Internal\Explanator;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Test;

#[Test]
#[Covers(Explanator::class)]
final class ExplanatorTest
{
    /**
     * Each row drives one distinct diagnostic combination. The expected list is the exact,
     * ordered set of {@see Report} classes {@see Explanator} appends — block order is
     * variance, outliers, skew, bimodal, noisy-environment, low-iter-time.
     *
     * @param list<class-string<Report>> $expected
     */
    #[DataSet([25.0, 5.0, 1, 0, 10, 5.0, [Report\UnreliableLowIterTime::class, Report\InsufficientIterTime::class]], 'rstdev>20 & low iter -> unreliable + insufficient')]
    #[DataSet([25.0, 50.0, 1, 0, 10, 50.0, [Report\VeryHighVariance::class, Report\NoisyEnvironment::class]], 'rstdev>20 & normal iter -> very-high + noisy')]
    #[DataSet([15.0, 5.0, 1, 0, 10, 5.0, [Report\HighVarianceLowIterTime::class, Report\InsufficientIterTime::class]], 'rstdev 10-20 & low iter -> high-low + insufficient')]
    #[DataSet([15.0, 50.0, 1, 0, 10, 50.0, [Report\HighVariance::class, Report\NoisyEnvironment::class]], 'rstdev 10-20 & normal iter -> high + noisy')]
    #[DataSet([20.0, 50.0, 1, 0, 10, 50.0, [Report\HighVariance::class, Report\NoisyEnvironment::class]], 'rstdev exactly 20 stays in the 10-20 band')]
    #[DataSet([10.0, 50.0, 1, 0, 10, 50.0, [Report\HighVariance::class]], 'rstdev exactly 10 -> high variance, noisy needs >10 so it stays off')]
    #[DataSet([10.0, 5.0, 1, 0, 10, 5.0, [Report\HighVarianceLowIterTime::class]], 'rstdev exactly 10 & low iter -> high-low only, low-iter-time notice stays off')]
    #[DataSet([15.0, 50.0, 1, 3, 10, 50.0, [Report\HighVariance::class, Report\ExtremeOutlierRate::class]], 'outliers suppress the noisy-environment arm')]
    #[DataSet([1.0, 50.0, 1, 3, 10, 50.0, [Report\ExtremeOutlierRate::class]], 'outliers>=3 & rate>20 -> extreme')]
    #[DataSet([1.0, 50.0, 1, 3, 20, 50.0, [Report\TooManyOutliers::class]], 'outliers>=3 & rate 15 -> too-many')]
    #[DataSet([1.0, 50.0, 1, 4, 20, 50.0, [Report\TooManyOutliers::class]], 'rate exactly 20 stays too-many, not extreme')]
    #[DataSet([1.0, 50.0, 1, 3, 30, 50.0, [Report\TooManyOutliers::class]], 'rate exactly 10 is inclusive -> too-many')]
    #[DataSet([1.0, 50.0, 1, 2, 4, 50.0, []], 'fewer than 3 absolute outliers never flags even at 50% rate')]
    #[DataSet([1.0, 50.0, 1, 0, 10, 40.0, [Report\HeavilySkewed::class]], 'skew>10 -> heavily skewed')]
    #[DataSet([1.0, 50.0, 1, 0, 10, 47.0, [Report\SkewedDistribution::class]], 'skew 5-10 -> skewed distribution')]
    #[DataSet([1.0, 52.5, 1, 0, 10, 50.0, [Report\SkewedDistribution::class]], 'skew exactly 5 is inclusive -> skewed')]
    #[DataSet([1.0, 55.0, 1, 0, 10, 50.0, [Report\SkewedDistribution::class]], 'skew exactly 10 stays skewed, not heavily')]
    #[DataSet([1.0, 60.0, 1, 5, 20, 50.0, [Report\ExtremeOutlierRate::class, Report\HeavilySkewed::class, Report\BimodalBehavior::class]], 'extreme outliers + heavy skew -> bimodal compound')]
    #[DataSet([1.0, 55.0, 1, 4, 20, 50.0, [Report\TooManyOutliers::class, Report\SkewedDistribution::class, Report\BimodalBehavior::class]], 'moderate outliers + skew -> bimodal compound')]
    #[DataSet([1.0, 55.0, 1, 3, 30, 50.0, [Report\TooManyOutliers::class, Report\SkewedDistribution::class]], 'outlier rate exactly 10 keeps bimodal off (needs >10)')]
    #[DataSet([5.0, 5.0, 1, 0, 10, 5.0, [Report\LowIterTime::class]], 'low iter time with acceptable variance -> notice')]
    #[DataSet([5.0, 4.0, 2, 0, 10, 4.0, [Report\LowIterTime::class]], 'iter time is favg*calls: 4*2=8 stays under 10')]
    #[DataSet([5.0, 4.0, 3, 0, 10, 4.0, []], 'iter time is favg*calls: 4*3=12 clears the threshold')]
    #[DataSet([5.0, 50.0, 1, 0, 10, 50.0, []], 'healthy result -> no reports')]
    #[DataSet([5.0, 50.0, 1, 0, 10, 0.0, []], 'zero median short-circuits skew to 0')]
    public function explainEmitsExpectedReports(
        float $frstdev,
        float $favg,
        int $calls,
        int $rejected,
        int $total,
        float $med,
        array $expected,
    ): void {
        $actual = \array_map(
            static fn(Report $r): string => $r::class,
            self::reportsFor($frstdev, $favg, $calls, $rejected, $total, $med),
        );

        Assert::same($actual, $expected);
    }

    public function prepareLinesSortsByTimeRanksPlacesAndPairsNamesToTheRightCase(): void
    {
        # Declaration order is current, alpha, beta; favg makes alpha fastest and beta slowest.
        $cases = [
            new CaseSet('current', [new Snap(1, 0, 0.0)]),
            new CaseSet('alpha', [new Snap(1, 0, 0.0)]),
            new CaseSet('beta', [new Snap(1, 0, 0.0)]),
        ];
        $results = [
            new CaseResult(mean: 100.0, med: 100.0, rstdev: 2.0, rejected: 0, favg: 100.0, frstdev: 1.0),
            new CaseResult(mean: 40.0, med: 50.0, rstdev: 3.0, rejected: 0, favg: 50.0, frstdev: 1.0),
            new CaseResult(mean: 220.0, med: 200.0, rstdev: 4.0, rejected: 0, favg: 200.0, frstdev: 1.0),
        ];

        $lines = Explanator::prepareLines($cases, $results);

        # Lines come back in declaration order (key preserved), names paired to their own case.
        Assert::same($lines[0]->name, 'current');
        Assert::same($lines[1]->name, 'alpha');
        Assert::same($lines[2]->name, 'beta');

        # Place reflects the favg ranking, not declaration order.
        Assert::same($lines[0]->place, 2);
        Assert::same($lines[1]->place, 1);
        Assert::same($lines[2]->place, 3);

        # Diffs are relative to the baseline (declaration index 0 = current), not the fastest.
        Assert::same($lines[0]->avg->value, 100.0);
        Assert::same($lines[0]->avg->diff, 0.0);
        Assert::same($lines[0]->med->diff, 0.0);
        Assert::same($lines[0]->favg->diff, 0.0);

        Assert::same($lines[1]->avg->value, 40.0);
        Assert::same($lines[1]->avg->diff, -60.0);
        Assert::same($lines[1]->med->diff, -50.0);
        Assert::same($lines[1]->favg->diff, -50.0);

        Assert::same($lines[2]->avg->value, 220.0);
        Assert::same($lines[2]->avg->diff, 120.0);
        Assert::same($lines[2]->med->diff, 100.0);
        Assert::same($lines[2]->favg->diff, 100.0);

        # Passthrough fields stay with their case.
        Assert::same($lines[1]->rstdev, 3.0);
        Assert::same($lines[2]->rstdev, 4.0);
        Assert::same($lines[0]->reports, []);
        Assert::same($lines[1]->reports, []);
        Assert::same($lines[2]->reports, []);
    }

    public function prepareLinesZeroBaselineYieldsZeroDiffsInsteadOfDividingByZero(): void
    {
        $cases = [
            new CaseSet('current', [new Snap(1, 0, 0.0)]),
            new CaseSet('other', [new Snap(1, 0, 0.0)]),
        ];
        $results = [
            new CaseResult(mean: 0.0, med: 0.0, rstdev: 0.0, rejected: 0, favg: 0.0, frstdev: 0.0),
            new CaseResult(mean: 80.0, med: 80.0, rstdev: 1.0, rejected: 0, favg: 80.0, frstdev: 1.0),
        ];

        $lines = Explanator::prepareLines($cases, $results);

        # Baseline (current) is all zero, so every relative diff falls back to 0.0.
        Assert::same($lines[1]->avg->value, 80.0);
        Assert::same($lines[1]->avg->diff, 0.0);
        Assert::same($lines[1]->med->diff, 0.0);
        Assert::same($lines[1]->favg->diff, 0.0);
        Assert::same($lines[0]->avg->diff, 0.0);
    }

    /**
     * @return list<Report>
     */
    private static function reportsFor(
        float $frstdev,
        float $favg,
        int $calls,
        int $rejected,
        int $total,
        float $med,
    ): array {
        $result = new CaseResult(
            mean: $favg,
            med: $med,
            rstdev: 0.0,
            rejected: $rejected,
            favg: $favg,
            frstdev: $frstdev,
        );
        $set = new CaseSet('probe', \array_fill(0, $total, new Snap($calls, 0, 0.0)));

        return Explanator::prepareLines([$set], [$result])[0]->reports;
    }
}
