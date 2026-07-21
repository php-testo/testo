<?php

declare(strict_types=1);

namespace Tests\Async\Self;

use Testo\Assert;
use Testo\Async\Concurrent;
use Testo\Async\Internal\ConcurrentRunInterceptor;
use Testo\Async\RunInCoroutine;
use Testo\Async\Strategy;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * Self-test: `#[Concurrent(Sequential)]` runs the case's tests one after another (v1 pass-through), and
 * a method-level `#[RunInCoroutine]` still composes — it gets its own coroutine and runs inside a fiber.
 */
#[Test]
#[Concurrent(Strategy::Sequential)]
#[Covers(Concurrent::class)]
#[Covers(ConcurrentRunInterceptor::class)]
final class ConcurrentSequentialTest
{
    public function plainTestRunsSequentially(): void
    {
        # v1 sequential is a pass-through, so a plain test is not wrapped in a coroutine.
        Assert::null(\Fiber::getCurrent());
    }

    #[RunInCoroutine]
    public function asyncMethodComposesWithConcurrent(): void
    {
        Assert::notNull(\Fiber::getCurrent());
    }
}
