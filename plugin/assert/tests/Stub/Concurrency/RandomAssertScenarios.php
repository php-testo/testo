<?php

declare(strict_types=1);

namespace Tests\Assert\Stub\Concurrency;

use Testo\Assert;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Test;

/**
 * Same distinct-count assertion isolation as {@see RoundRobinAssertScenarios}, but under
 * {@see Schedule::Random}: the scheduler steps one random ready fiber each round, so the interleave order
 * is non-deterministic. The per-test count each test ends up with must stay deterministic regardless —
 * that is exactly what proves the assert collector's state swapping is not affected by the switching order.
 */
#[Test]
#[RunInFiber(Schedule::Random)]
final class RandomAssertScenarios
{
    public function recordsThreeAssertions(): void
    {
        self::assertAcrossSuspends(3);
    }

    public function recordsFourAssertions(): void
    {
        self::assertAcrossSuspends(4);
    }

    public function recordsFiveAssertions(): void
    {
        self::assertAcrossSuspends(5);
    }

    /**
     * Record exactly `$count` passing assertions, suspending after each so the other tests interleave.
     */
    private static function assertAcrossSuspends(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Assert::same($i, $i);
            \Fiber::suspend();
        }
    }
}
