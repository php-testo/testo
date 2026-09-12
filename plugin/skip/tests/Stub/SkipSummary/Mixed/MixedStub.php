<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\SkipSummary\Mixed;

use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Test;
use Testo\Skip;

/**
 * One directory with a passing, a failing and two skipped tests, so the summary arithmetic
 * `total = passed + failed + skipped` can be pinned. Kept out of the shared `Stub/Skip` directory:
 * {@see \Tests\Skip\Feature\SkipSummaryTest} asserts exact per-status counts, so the set of
 * outcomes here has to stay closed.
 */
#[Test]
final class MixedStub
{
    /**
     * Data sets for {@see self::skippedDataDriven()}; never called, since that test is skipped.
     *
     * @return iterable<array{int}>
     */
    public static function provide(): iterable
    {
        yield [1];
        yield [2];
    }

    public function passes(): void
    {
        Assert::true(true);
    }

    public function fails(): void
    {
        # Controlled failure: the skipped tests must not hide it from the totals.
        Assert::true(false);
    }

    #[Skip('skipped in the mixed case')]
    public function skipped(): void
    {
        throw new \LogicException('Must never run: the test is skipped.');
    }

    #[Skip('data-driven test skipped as a whole')]
    #[DataProvider('provide')]
    public function skippedDataDriven(int $value): void
    {
        throw new \LogicException('Must never run: the test is skipped.');
    }
}
