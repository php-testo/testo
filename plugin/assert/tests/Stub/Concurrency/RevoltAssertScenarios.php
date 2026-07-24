<?php

declare(strict_types=1);

namespace Tests\Assert\Stub\Concurrency;

use Revolt\EventLoop;
use Testo\Assert;
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Bridge\Revolt\Strategy;
use Testo\Test;

/**
 * The same distinct-count assertion isolation as {@see RoundRobinAssertScenarios}, but on a **real Revolt
 * loop** ({@see Strategy::PerCase}): all three tests are launched at once and interleave at genuine await
 * points (a timer per assertion, far more than two suspensions each). Each test must still find exactly its
 * own assertions in its {@see \Testo\Assert\TestState} — proving the fiber-local collector survives real
 * event-loop scheduling, where the loop resumes each test's own fiber directly.
 */
#[Test]
#[RunInRevolt(Strategy::PerCase)]
final class RevoltAssertScenarios
{
    public function recordsThreeAssertions(): void
    {
        self::assertAcrossAwaits(3);
    }

    public function recordsFourAssertions(): void
    {
        self::assertAcrossAwaits(4);
    }

    public function recordsFiveAssertions(): void
    {
        self::assertAcrossAwaits(5);
    }

    /**
     * Record exactly `$count` passing assertions, awaiting a real timer after each so the other tests
     * genuinely interleave on the loop.
     */
    private static function assertAcrossAwaits(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Assert::same($i, $i);

            $suspension = EventLoop::getSuspension();
            EventLoop::delay(0.001, static fn() => $suspension->resume());
            $suspension->suspend();
        }
    }
}
