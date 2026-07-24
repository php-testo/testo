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
 * Demonstrative — **EXPECTED RED on this branch.**
 *
 * A class-level `#[RunInRevolt(Strategy::PerCase)]` launches the case's tests concurrently on the
 * Revolt loop. With the current, non-fiber-local guards the per-test pipeline runs inside a loop fiber
 * where the fiber-aware guards hand-drive a nested fiber and fight the Revolt driver for it, so this
 * deadlocks / clashes state (`Event loop terminated without resuming the current suspension`).
 *
 * Left failing on purpose to mark the blocker: real concurrent PerCase needs the guards to move to
 * fiber-local storage, which is done on the **main branch**, not here.
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
