<?php

declare(strict_types=1);

namespace Tests\Bridge\Mockery\Stub;

use Mockery\MockInterface;
use Testo\Expect;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Test;

/**
 * Two Mockery tests interleaving on Testo's fiber scheduler ({@see Schedule::RoundRobin}), each declaring
 * an expected exception up front and throwing it after the yield. If the bridge is transparent, both pass:
 * the expectation is registered on the test's own state and the throw satisfies it.
 *
 * Driven through {@see \Testo\Testing\Helper\TestRunner} by the Feature suite.
 */
#[Test]
#[RunInFiber(Schedule::RoundRobin)]
final class MockeryExpectConcurrencyScenarios
{
    public function firstExpectsItsException(): never
    {
        Expect::exception(\DomainException::class);

        /** @var MockInterface&\Countable $mock */
        $mock = \Mockery::mock(\Countable::class);
        $mock->expects('count')->once()->andReturn(1);

        \Fiber::suspend();

        $mock->count();
        throw new \DomainException('first');
    }

    public function secondExpectsItsException(): never
    {
        Expect::exception(\DomainException::class);

        /** @var MockInterface&\Countable $mock */
        $mock = \Mockery::mock(\Countable::class);
        $mock->expects('count')->once()->andReturn(2);

        \Fiber::suspend();

        $mock->count();
        throw new \DomainException('second');
    }
}
