<?php

declare(strict_types=1);

namespace Tests\Bridge\Mockery\Stub;

use Mockery\MockInterface;
use Testo\Assert;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Test;

/**
 * Two Mockery tests interleaving on Testo's fiber scheduler ({@see Schedule::RoundRobin}), each making
 * plain `Assert::*` calls in its body — one before the yield, one after. If the bridge is transparent,
 * every test's history holds all of its own assertions plus the bridge's mock-verification record.
 *
 * Driven through {@see \Testo\Testing\Helper\TestRunner} by the Feature suite.
 */
#[Test]
#[RunInFiber(Schedule::RoundRobin)]
final class MockeryAssertConcurrencyScenarios
{
    public function firstAssertsAroundItsMock(): void
    {
        /** @var MockInterface&\Countable $mock */
        $mock = \Mockery::mock(\Countable::class);
        $mock->expects('count')->once()->andReturn(1);

        Assert::same(1, 1);

        \Fiber::suspend();

        Assert::same($mock->count(), 1);
    }

    public function secondAssertsAroundItsMock(): void
    {
        /** @var MockInterface&\Countable $mock */
        $mock = \Mockery::mock(\Countable::class);
        $mock->expects('count')->once()->andReturn(2);

        Assert::same(2, 2);

        \Fiber::suspend();

        Assert::same($mock->count(), 2);
    }
}
