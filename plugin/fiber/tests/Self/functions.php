<?php

declare(strict_types=1);

namespace Tests\Fiber\Self;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Fiber\Coroutine;
use Testo\Fiber\Internal\CoroutineScopeInterceptor;
use Testo\Fiber\Internal\RunInFiberInterceptor;
use Testo\Fiber\RunInFiber;
use Testo\Filter\Group;
use Testo\Test;

/**
 * `spawn()` only schedules the coroutine — it takes its first step once the body yields at `await()`,
 * so the recorded order is body-first, and observing it at all proves the scheduler drives a fiber
 * opened for a free function.
 */
#[Test]
#[RunInFiber]
#[Group('async')]
#[Covers(RunInFiber::class)]
#[Covers(RunInFiberInterceptor::class)]
#[Covers(CoroutineScopeInterceptor::class)]
function functionLevelRunsInAFiber(): void
{
    Assert::notNull(\Fiber::getCurrent());

    $log = [];

    $task = Coroutine::spawn(static function () use (&$log): int {
        $log[] = 'coroutine:start';
        \Fiber::suspend();
        $log[] = 'coroutine:resumed';

        return 42;
    });

    $log[] = 'body';

    Assert::same($task->await(), 42);
    Assert::same($log, ['body', 'coroutine:start', 'coroutine:resumed']);
}
