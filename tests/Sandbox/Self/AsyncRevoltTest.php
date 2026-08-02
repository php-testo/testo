<?php

declare(strict_types=1);

namespace Tests\Sandbox\Self;

use Revolt\EventLoop;
use Testo\Assert;
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Bridge\Revolt\Strategy;
use Testo\Test;

/**
 * Async sandbox: several tests run at once on one Revolt loop ({@see Strategy::PerCase}), each doing a few
 * steps of real work-and-sleep. Because they share the loop and sleep for different amounts, they
 * interleave and finish in a staggered order — handy for eyeballing concurrent output.
 *
 * The loop-free counterpart is {@see AsyncTest}, which interleaves the same way on Testo's own fiber
 * scheduler: this one is here for the case where the naps are real timers rather than bare suspends.
 */
#[Test]
#[RunInRevolt(Strategy::PerCase)]
final class AsyncRevoltTest
{
    /**
     * Per-test sleep step, in seconds. Each test naps this long between steps, so the shorter-napping
     * tests finish well before the longer-napping ones.
     *
     * @var array<non-empty-string, float>
     */
    private const SLEEP_SECONDS = [
        'quick' => 0.10,
        'steady' => 0.20,
        'slow' => 0.35,
    ];

    /** How many work-and-sleep steps each test performs. */
    private const STEPS = 5;

    public function quickBursts(): void
    {
        self::workThenSleep('quick');
    }

    public function steadyProgress(): void
    {
        self::workThenSleep('steady');
    }

    public function slowGrind(): void
    {
        self::workThenSleep('slow');
    }

    /**
     * Do {@see STEPS} steps of "work" (an echoed progress line) separated by a real timer sleep of the
     * `$label`'s duration, yielding the loop each time so the other tests run in between.
     */
    private static function workThenSleep(string $label): void
    {
        $step = self::SLEEP_SECONDS[$label];

        $done = 0;
        for ($i = 1; $i <= self::STEPS; $i++) {
            self::sleep($step);
            ++$done;
            echo \sprintf("[%s] step %d/%d (slept %.2fs)\n", $label, $i, self::STEPS, $step);
        }

        Assert::same($done, self::STEPS);
    }

    /**
     * Suspend this test's fiber for `$seconds` on the Revolt loop, letting the sibling tests run while it
     * naps, then resume once the timer fires.
     */
    private static function sleep(float $seconds): void
    {
        $suspension = EventLoop::getSuspension();
        EventLoop::delay($seconds, static fn() => $suspension->resume());
        $suspension->suspend();
    }
}
