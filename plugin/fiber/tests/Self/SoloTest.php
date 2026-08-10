<?php

declare(strict_types=1);

namespace Tests\Fiber\Self;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Fiber\Internal\RunInFiberInterceptor;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Test;

/**
 * Self-test: `#[RunInFiber(Schedule::Solo)]` runs each test alone in its own fiber, one after another
 * — no interleaving. Each test executes inside a fiber, and a `\Fiber::suspend()` does not hand control
 * to another test (the current one is driven to completion first).
 */
#[Test]
#[RunInFiber(Schedule::Solo)]
#[Covers(RunInFiber::class)]
#[Covers(RunInFiberInterceptor::class)]
final class SoloTest
{
    /** @var list<string> */
    private static array $log = [];

    public function firstRunsInItsOwnFiberToCompletion(): void
    {
        Assert::notNull(\Fiber::getCurrent());

        self::$log[] = 'first.1';
        \Fiber::suspend(); // under Solo this just resumes the same fiber — no other test runs in between
        self::$log[] = 'first.2';
    }

    public function secondSeesFirstFullyFinished(): void
    {
        Assert::notNull(\Fiber::getCurrent());

        # Solo: "first" ran to completion before "second" started, despite the suspend().
        Assert::same(self::$log, ['first.1', 'first.2']);
    }
}
