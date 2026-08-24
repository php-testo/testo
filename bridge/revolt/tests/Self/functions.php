<?php

declare(strict_types=1);

namespace Tests\Bridge\Revolt\Self;

use Revolt\EventLoop;
use Testo\Assert;
use Testo\Bridge\Revolt\Internal\RunInRevoltInterceptor;
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Codecov\Covers;
use Testo\Filter\Group;
use Testo\Test;

/**
 * The suspend/resume round-trip only completes because the free function itself runs on the Revolt
 * loop — a bare fiber with no loop would leave the timer's resume unfired.
 */
#[Test]
#[RunInRevolt]
#[Group('async')]
#[Covers(RunInRevolt::class)]
#[Covers(RunInRevoltInterceptor::class)]
function functionLevelRunsOnTheLoop(): void
{
    Assert::notNull(\Fiber::getCurrent());

    $suspension = EventLoop::getSuspension();
    EventLoop::delay(0.001, static fn() => $suspension->resume('resumed'));

    Assert::same($suspension->suspend(), 'resumed');
}
