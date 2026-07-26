<?php

declare(strict_types=1);

namespace Tests\Sandbox\Self;

use Testo\Assert;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Test;

/**
 * Concurrency sandbox: the case's tests run together on Testo's cooperative fiber scheduler
 * ({@see Schedule::RoundRobin}), each doing a few steps of real work-and-yield. Because they share the
 * scheduler and take a different number of steps, they interleave and finish in a staggered order —
 * handy for eyeballing concurrent output.
 */
#[Test]
#[RunInFiber(Schedule::RoundRobin)]
final class AsyncTest
{
    /**
     * Per-test work profile: how many work-and-yield steps the test takes, and how long a single step
     * of "work" naps. Step counts differ so the shorter tests finish well before the longer ones,
     * while round-robin gives every test one step per round.
     *
     * @var array<non-empty-string, array{steps: int<1, max>, nap: int<0, max>}>
     */
    private const PROFILES = [
        'quick' => ['steps' => 3, 'nap' => 100_000],
        'steady' => ['steps' => 5, 'nap' => 150_000],
        'slow' => ['steps' => 8, 'nap' => 200_000],
    ];

    public function quickBursts(): void
    {
        self::workThenYield('quick');
    }

    public function steadyProgress(): void
    {
        self::workThenYield('steady');
    }

    public function slowGrind(): void
    {
        self::workThenYield('slow');
    }

    /**
     * Do the `$label` profile's steps of "work" (a nap plus an echoed progress line), suspending the
     * fiber after each one so the sibling tests take their step in between.
     *
     * @param non-empty-string $label
     */
    private static function workThenYield(string $label): void
    {
        ['steps' => $steps, 'nap' => $nap] = self::PROFILES[$label];

        $done = 0;
        for ($i = 1; $i <= $steps; $i++) {
            \usleep($nap);
            ++$done;
            echo \sprintf("[%s] step %d/%d (worked %.2fs)\n", $label, $i, $steps, $nap / 1_000_000);
            \Fiber::suspend();
        }

        Assert::same($done, $steps);
    }
}
