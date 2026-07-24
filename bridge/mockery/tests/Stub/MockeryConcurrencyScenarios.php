<?php

declare(strict_types=1);

namespace Tests\Bridge\Mockery\Stub;

use Mockery\MockInterface;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Test;

/**
 * Two Mockery tests forced to **interleave** on Testo's fiber scheduler ({@see Schedule::RoundRobin}):
 * each sets up a mock, yields at `\Fiber::suspend()`, then uses it. Because Mockery's container is
 * process-global, the second test to start (while the first is parked mid-mock) must be rejected with a
 * {@see \Testo\Bridge\Mockery\MockeryConcurrencyException}. Driven through {@see
 * \Testo\Testing\Helper\TestRunner} by the Feature suite.
 */
#[Test]
#[RunInFiber(Schedule::RoundRobin)]
final class MockeryConcurrencyScenarios
{
    public function firstMockAcrossSuspend(): void
    {
        /** @var MockInterface&\Countable $mock */
        $mock = \Mockery::mock(\Countable::class);
        $mock->expects('count')->once()->andReturn(1);

        \Fiber::suspend();

        $mock->count();
    }

    public function secondMockAcrossSuspend(): void
    {
        /** @var MockInterface&\Countable $mock */
        $mock = \Mockery::mock(\Countable::class);
        $mock->expects('count')->once()->andReturn(2);

        \Fiber::suspend();

        $mock->count();
    }
}
