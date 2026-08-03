<?php

declare(strict_types=1);

namespace Tests\Assert\Stub\Concurrency;

use Revolt\EventLoop;
use Testo\Assert;
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Test;

/**
 * Assertion collection on a **real Revolt loop**, where {@see RunInRevolt} gives the whole loop run to one
 * test at a time. Each test records a distinct number of assertions, awaiting a genuine timer between them,
 * and makes the last one inside a **coroutine of its own** that it starts on the loop.
 *
 * That last one is the point. The collector opens its scope outside the loop and never suspends, so for
 * the whole dispatch its active state is this test's — and the spawned coroutine, which holds no scope of
 * its own, reads exactly that. Its assertion must land in the test's {@see \Testo\Assert\TestState} like
 * any other, and the test must still find exactly its own count. Interleaving whole tests is
 * {@see RoundRobinAssertScenarios}' job, on fibers Testo drives itself.
 */
#[Test]
#[RunInRevolt]
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
     * Record exactly `$count` passing assertions: all but the last from the test body, awaiting a real
     * timer after each, and the last one from a spawned coroutine.
     */
    private static function assertAcrossAwaits(int $count): void
    {
        for ($i = 1; $i < $count; $i++) {
            Assert::same($i, $i);
            self::await();
        }

        self::inCoroutine(static fn() => Assert::same($count, $count));
    }

    /**
     * Park on a real timer, letting the loop run while we wait.
     */
    private static function await(): void
    {
        $suspension = EventLoop::getSuspension();
        EventLoop::delay(0.001, static fn() => $suspension->resume());
        $suspension->suspend();
    }

    /**
     * Run $body as a coroutine of its own on the loop and block until it finishes.
     */
    private static function inCoroutine(\Closure $body): void
    {
        $suspension = EventLoop::getSuspension();

        EventLoop::queue(static function () use ($suspension, $body): void {
            try {
                $body();
                $suspension->resume();
            } catch (\Throwable $e) {
                $suspension->throw($e);
            }
        });

        $suspension->suspend();
    }
}
