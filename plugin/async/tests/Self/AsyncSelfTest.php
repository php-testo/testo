<?php

declare(strict_types=1);

namespace Tests\Async\Self;

use Revolt\EventLoop;
use Testo\Assert;
use Testo\Async;
use Testo\Async\Internal\AsyncRunInterceptor;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * Self-test: an `#[Async]` test runs as a coroutine on the Revolt event loop — it executes inside a
 * fiber and can await real async work (a timer) and resume without blocking the process.
 */
#[Test]
#[Async]
#[Covers(Async::class)]
#[Covers(AsyncRunInterceptor::class)]
final class AsyncSelfTest
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
