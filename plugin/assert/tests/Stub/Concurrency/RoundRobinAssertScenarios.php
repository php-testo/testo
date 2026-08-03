<?php

declare(strict_types=1);

namespace Tests\Assert\Stub\Concurrency;

use Testo\Assert;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Test;

/**
 * Three tests interleaved on Testo's cooperative fiber scheduler ({@see Schedule::RoundRobin}), each
 * recording a **distinct number** of passing assertions and yielding with `\Fiber::suspend()` after every
 * one — so a sibling runs between each of this test's assertions. The assert collector guards one active slot
 * ({@see \Testo\Assert\Internal\StaticState}); if it leaked across fibers, a test's own history would end
 * up with the wrong count. Driven through {@see \Testo\Testing\Helper\TestRunner} by the Feature suite,
 * which reads each test's {@see \Testo\Assert\TestState} back off the result and checks the exact count.
 */
#[Test]
#[RunInFiber(Schedule::RoundRobin)]
final class RoundRobinAssertScenarios
{
    public function recordsThreeAssertions(): void
    {
        self::assertAcrossSuspends(3);
    }

    public function recordsFourAssertions(): void
    {
        self::assertAcrossSuspends(4);
    }

    public function recordsFiveAssertions(): void
    {
        self::assertAcrossSuspends(5);
    }

    /**
     * Record exactly `$count` passing assertions, suspending after each so the other tests interleave.
     */
    private static function assertAcrossSuspends(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Assert::same($i, $i);
            \Fiber::suspend();
        }
    }
}
