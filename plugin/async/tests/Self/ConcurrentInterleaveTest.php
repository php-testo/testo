<?php

declare(strict_types=1);

namespace Tests\Async\Self;

use Testo\Assert;
use Testo\Async\Coroutine;
use Testo\Async\Internal\ConcurrentRunInterceptor;
use Testo\Async\Internal\Scheduler;
use Testo\Async\Strategy;
use Testo\Codecov\Covers;
use Testo\Concurrent;
use Testo\Test;

/**
 * Self-test: `#[Concurrent(RoundRobin)]` interleaves the case's tests end-to-end through the real
 * pipeline, switching at {@see Coroutine::reschedule()} points. The shared log proves the second test
 * takes a step between the first test's two steps, and each test's own assertions stay isolated.
 */
#[Test]
#[Concurrent(Strategy::RoundRobin)]
#[Covers(Concurrent::class)]
#[Covers(ConcurrentRunInterceptor::class)]
#[Covers(Scheduler::class)]
final class ConcurrentInterleaveTest
{
    /** @var list<string> */
    private static array $log = [];

    public function first(): void
    {
        self::$log[] = 'first.1';
        Coroutine::reschedule();
        self::$log[] = 'first.2';

        # Round-robin: after the yield, "second" has had its first step in between.
        Assert::same(self::$log, ['first.1', 'second.1', 'first.2']);
    }

    public function second(): void
    {
        self::$log[] = 'second.1';
        Coroutine::reschedule();
        self::$log[] = 'second.2';

        Assert::same(self::$log, ['first.1', 'second.1', 'first.2', 'second.2']);
    }
}
