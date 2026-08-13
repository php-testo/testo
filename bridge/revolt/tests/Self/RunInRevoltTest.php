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
 *
 * {@see awaitsATimerAndResumes()} doubles as the guard against an interceptor being scheduled inner to
 * the loop dispatch: one that suspends out of the loop fiber (the coverage trampoline, were it ordered
 * innermost) leaves the awaited suspension unresumed and aborts the test — but only on a coverage run,
 * so it takes `composer test:cc` to see it.
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
