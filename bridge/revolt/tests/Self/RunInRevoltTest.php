<?php

declare(strict_types=1);

namespace Tests\Bridge\Revolt\Self;

use Revolt\EventLoop;
use Testo\Assert;
use Testo\Bridge\Revolt\Internal\RunInRevoltInterceptor;
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * Self-test: a `#[RunInRevolt]` test runs as a coroutine on the Revolt event loop — it executes inside
 * a fiber and can await real async work (a timer) and resume without blocking the process.
 */
#[Test]
#[RunInRevolt]
#[Covers(RunInRevolt::class)]
#[Covers(RunInRevoltInterceptor::class)]
final class RunInRevoltTest
{
    public function runsInsideAFiber(): void
    {
        Assert::notNull(\Fiber::getCurrent());
    }

    public function awaitsATimerAndResumes(): void
    {
        $suspension = EventLoop::getSuspension();
        EventLoop::delay(0.001, static fn() => $suspension->resume('resumed'));

        Assert::same($suspension->suspend(), 'resumed');
    }
}
