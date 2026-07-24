<?php

declare(strict_types=1);

namespace Tests\Bridge\Revolt\Self;

use Revolt\EventLoop;
use Testo\Assert;
use Testo\Bridge\Revolt\Internal\RevoltTestBatchRunner;
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Bridge\Revolt\Strategy;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * A class-level `#[RunInRevolt(Strategy::PerCase)]` launches the case's tests concurrently on the Revolt
 * loop: both start, both park on their own timer, and both resume once the loop drives the timers. The
 * per-test pipeline runs inside a loop fiber, and the fiber-local scoped-state guards keep each test's
 * assertion state isolated across the interleave — so both tests pass without clashing.
 */
#[Test]
#[RunInRevolt(Strategy::PerCase)]
#[Covers(RevoltTestBatchRunner::class)]
final class PerCaseParallelTest
{
    public function firstAwaitsATimer(): void
    {
        $suspension = EventLoop::getSuspension();
        EventLoop::delay(0.001, static fn() => $suspension->resume());
        $suspension->suspend();

        Assert::same(1, 1);
    }

    public function secondAwaitsATimer(): void
    {
        $suspension = EventLoop::getSuspension();
        EventLoop::delay(0.001, static fn() => $suspension->resume());
        $suspension->suspend();

        Assert::same(2, 2);
    }
}
