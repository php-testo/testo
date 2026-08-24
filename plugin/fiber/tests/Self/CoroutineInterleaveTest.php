<?php

declare(strict_types=1);

namespace Tests\Fiber\Self;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Fiber\Coroutine;
use Testo\Fiber\Internal\CoroutineScopeInterceptor;
use Testo\Fiber\Internal\Scheduler;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Filter\Group;
use Testo\Test;

/**
 * Self-test: coroutines spawned by a test share the schedule with the test body *and* — under a
 * class-level `#[RunInFiber(Schedule::RoundRobin)]` — with the case's other tests. The shared log
 * proves the full interleave order: the coroutine steps inside its test's rounds, the sibling test
 * steps between them, and `await()` parks the body without stalling anyone.
 */
#[Test]
#[RunInFiber(Schedule::RoundRobin)]
#[Group('async')]
#[Covers(Coroutine::class)]
#[Covers(CoroutineScopeInterceptor::class)]
#[Covers(Scheduler::class)]
final class CoroutineInterleaveTest
{
    /** @var list<string> */
    private static array $log = [];

    public function first(): void
    {
        self::$log[] = 'first.body.1';
        $echo = Coroutine::spawn(static function (): string {
            self::$log[] = 'first.co.1';
            \Fiber::suspend();
            self::$log[] = 'first.co.2';

            return 'echo';
        });
        \Fiber::suspend();

        self::$log[] = 'first.body.2';
        Assert::same($echo->await(), 'echo');

        Assert::same(self::$log, [
            'first.body.1',
            'first.co.1',
            'second.body.1',
            'first.body.2',
            'first.co.2',
            'second.body.2',
        ]);
    }

    public function second(): void
    {
        self::$log[] = 'second.body.1';
        \Fiber::suspend();
        self::$log[] = 'second.body.2';

        Assert::same(self::$log, [
            'first.body.1',
            'first.co.1',
            'second.body.1',
            'first.body.2',
            'first.co.2',
            'second.body.2',
        ]);
    }
}
