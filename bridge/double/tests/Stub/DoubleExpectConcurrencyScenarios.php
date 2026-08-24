<?php

declare(strict_types=1);

namespace Tests\Bridge\Double\Stub;

use JMac\Testing\Double;
use JMac\Testing\DoubleInterface;
use Testo\Expect;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Test;

/**
 * Two Double tests interleaving on Testo's fiber scheduler ({@see Schedule::RoundRobin}), each declaring
 * an expected exception up front and throwing it after the yield. If the bridge is transparent, both pass:
 * the expectation is registered on the test's own double and the throw satisfies the declared exception.
 *
 * Driven through {@see \Testo\Testing\Helper\TestRunner} by the Feature suite.
 */
#[Test]
#[RunInFiber(Schedule::RoundRobin)]
final class DoubleExpectConcurrencyScenarios
{
    public function firstExpectsItsException(): never
    {
        Expect::exception(\DomainException::class);

        /** @var DoubleInterface&\Countable $double */
        $double = Double::for(\Countable::class);
        $double->expects('count')->returns(1);

        \Fiber::suspend();

        $double->count();
        throw new \DomainException('first');
    }

    public function secondExpectsItsException(): never
    {
        Expect::exception(\DomainException::class);

        /** @var DoubleInterface&\Countable $double */
        $double = Double::for(\Countable::class);
        $double->expects('count')->returns(2);

        \Fiber::suspend();

        $double->count();
        throw new \DomainException('second');
    }
}
