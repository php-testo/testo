<?php

declare(strict_types=1);

namespace Tests\Test\Stub\SkipSummary\Mixed;

use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Test;
use Testo\Test\Skip;

/**
 * One catalog with every outcome kind, so the summary arithmetic
 * `total = passed + failed + skipped` can be pinned. Not part of the Stub/Skip catalog:
 * the deliberately failing test would turn the feature runs red.
 */
#[Test]
final class SummaryMixedStub
{
    public function passes(): void
    {
        Assert::true(true);
    }

    public function fails(): void
    {
        // Controlled failure: the parked tests must not hide it from the totals.
        Assert::true(false);
    }

    #[Skip('parked in the mixed case')]
    public function parked(): void
    {
        throw new \LogicException('Must never run: the test is parked.');
    }

    #[Skip('data-driven test parked as a whole')]
    #[DataProvider('provide')]
    public function parkedDataDriven(int $value): void
    {
        throw new \LogicException('Must never run: the test is parked.');
    }

    public static function provide(): iterable
    {
        yield [1];
        yield [2];
    }
}
