<?php

declare(strict_types=1);

namespace Tests\Bridge\Double\Self;

use JMac\Testing\Double;
use JMac\Testing\DoubleInterface;
use Revolt\EventLoop;
use Testo\Assert;
use Testo\Bridge\Double\DoublePlugin;
use Testo\Bridge\Double\Internal\DoubleInterceptor;
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Codecov\Covers;
use Testo\Fiber\RunInFiber;
use Testo\Filter\Group;
use Testo\Test;

/**
 * Double under one-at-a-time fiber execution. A double set up inside a scheduler fiber (Solo) and one
 * carried across a real Revolt await both verify normally: with no sibling test running, the
 * process-global pending list stays this test's throughout.
 */
#[Test]
#[Group('async')]
#[Covers(DoublePlugin::class)]
#[Covers(DoubleInterceptor::class)]
final class DoubleUnderFibersTest
{
    #[RunInFiber]
    public function doubleVerifiesInsideASoloFiber(): void
    {
        /** @var DoubleInterface&\Countable $double */
        $double = Double::for(\Countable::class);
        $double->expects('count')->returns(4);

        Assert::same($double->count(), 4);
    }

    #[RunInRevolt]
    public function doubleVerifiesAcrossARevoltAwait(): void
    {
        /** @var DoubleInterface&\Countable $double */
        $double = Double::for(\Countable::class);
        $double->expects('count')->returns(7);

        $suspension = EventLoop::getSuspension();
        EventLoop::delay(0.001, static fn() => $suspension->resume());
        $suspension->suspend();

        Assert::same($double->count(), 7);
    }
}
