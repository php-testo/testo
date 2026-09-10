<?php

declare(strict_types=1);

namespace Tests\Bridge\Double\Stub;

use JMac\Testing\Double;
use JMac\Testing\DoubleInterface;
use Testo\Assert;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Test;

/**
 * Two Double tests interleaving on {@see Schedule::RoundRobin}, each asserting before and after its yield.
 * Driven through {@see \Testo\Testing\Helper\TestRunner} by the Feature suite.
 */
#[Test]
#[RunInFiber(Schedule::RoundRobin)]
final class DoubleAssertConcurrencyScenarios
{
    public function firstAssertsAroundItsDouble(): void
    {
        /** @var DoubleInterface&\Countable $double */
        $double = Double::for(\Countable::class);
        $double->expects('count')->returns(1);

        Assert::same(1, 1);

        \Fiber::suspend();

        Assert::same($double->count(), 1);
    }

    public function secondAssertsAroundItsDouble(): void
    {
        /** @var DoubleInterface&\Countable $double */
        $double = Double::for(\Countable::class);
        $double->expects('count')->returns(2);

        Assert::same(2, 2);

        \Fiber::suspend();

        Assert::same($double->count(), 2);
    }
}
