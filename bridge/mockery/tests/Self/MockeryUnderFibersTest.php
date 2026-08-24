<?php

declare(strict_types=1);

namespace Tests\Bridge\Mockery\Self;

use Revolt\EventLoop;
use Testo\Assert;
use Testo\Bridge\Mockery\Internal\MockeryInterceptor;
use Testo\Bridge\Mockery\MockeryPlugin;
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Codecov\Covers;
use Testo\Fiber\RunInFiber;
use Testo\Filter\Group;
use Testo\Test;

/**
 * Mockery under one-at-a-time fiber execution. A mock set up inside a scheduler fiber (Solo) and one
 * carried across a real Revolt await both verify normally: with no sibling test running, the
 * process-global container stays this test's throughout.
 */
#[Test]
#[Group('async')]
#[Covers(MockeryPlugin::class)]
#[Covers(MockeryInterceptor::class)]
final class MockeryUnderFibersTest
{
    #[RunInFiber]
    public function mockVerifiesInsideASoloFiber(): void
    {
        /** @var \Mockery\MockInterface&\Countable $mock */
        $mock = \Mockery::mock(\Countable::class);
        $mock->expects('count')->once()->andReturn(4);

        Assert::same($mock->count(), 4);
    }

    #[RunInRevolt]
    public function mockVerifiesAcrossARevoltAwait(): void
    {
        /** @var \Mockery\MockInterface&\Countable $mock */
        $mock = \Mockery::mock(\Countable::class);
        $mock->expects('count')->once()->andReturn(7);

        $suspension = EventLoop::getSuspension();
        EventLoop::delay(0.001, static fn() => $suspension->resume());
        $suspension->suspend();

        Assert::same($mock->count(), 7);
    }
}
