<?php

declare(strict_types=1);

namespace Tests\Fiber\Self;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Fiber\Coroutine;
use Testo\Fiber\Internal\RunInFiberInterceptor;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Test;

/**
 * Self-test: `#[RunInFiber(Schedule::OneByOne)]` runs each test in its own fiber, one after another —
 * no interleaving. Each test executes inside a fiber, and `reschedule()` does not hand control to
 * another test (the current one is driven to completion first).
 */
#[Test]
#[RunInFiber(Schedule::OneByOne)]
#[Covers(RunInFiber::class)]
#[Covers(RunInFiberInterceptor::class)]
final class OneByOneTest
{
    /** @var list<string> */
    private static array $log = [];

    public function firstRunsInItsOwnFiberToCompletion(): void
    {
        Assert::notNull(\Fiber::getCurrent());

        self::$log[] = 'first.1';
        Coroutine::reschedule(); // active but non-interleaving under OneByOne: resumes this same fiber
        self::$log[] = 'first.2';
    }

    public function secondSeesFirstFullyFinished(): void
    {
        Assert::notNull(\Fiber::getCurrent());

        # OneByOne: "first" ran to completion before "second" started, despite the reschedule().
        Assert::same(self::$log, ['first.1', 'first.2']);
    }
}
