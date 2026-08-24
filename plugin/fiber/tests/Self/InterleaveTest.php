<?php

declare(strict_types=1);

namespace Tests\Fiber\Self;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Fiber\Internal\RunInFiberInterceptor;
use Testo\Fiber\Internal\Scheduler;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Filter\Group;
use Testo\Test;

/**
 * Self-test: `#[RunInFiber(Schedule::RoundRobin)]` interleaves the case's tests end-to-end through the
 * real pipeline, switching wherever a test calls `\Fiber::suspend()`. The shared log proves the second
 * test takes a step between the first test's two steps, and each test's own assertions stay isolated.
 */
#[Test]
#[RunInFiber(Schedule::RoundRobin)]
#[Group('async')]
#[Covers(RunInFiber::class)]
#[Covers(RunInFiberInterceptor::class)]
#[Covers(Scheduler::class)]
final class InterleaveTest
{
    /** @var list<string> */
    private static array $log = [];

    public function first(): void
    {
        self::$log[] = 'first.1';
        \Fiber::suspend();
        self::$log[] = 'first.2';

        # Round-robin: after the yield, "second" has had its first step in between.
        Assert::same(self::$log, ['first.1', 'second.1', 'first.2']);
    }

    public function second(): void
    {
        self::$log[] = 'second.1';
        \Fiber::suspend();
        self::$log[] = 'second.2';

        Assert::same(self::$log, ['first.1', 'second.1', 'first.2', 'second.2']);
    }
}
