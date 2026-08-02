<?php

declare(strict_types=1);

namespace Tests\Sandbox\Self;

use Testo\Assert;
use Testo\Data\DataSet;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Filter\Group;
use Testo\Test;

/**
 * Concurrency sandbox: the case's tests run together on Testo's cooperative fiber scheduler
 * ({@see Schedule::RoundRobin}), each doing a few steps of real work-and-yield. Because they share the
 * scheduler and take a different number of steps, they interleave and finish in a staggered order —
 * handy for eyeballing concurrent output.
 *
 * Two of them ({@see slowDataSets}, {@see fastDataSets}) nest data sets under a batch node while
 * yielding to each other. That is the case a line-oriented report struggles with: printed as events
 * arrive, the two trees splice into one unreadable list, so each test's lines have to come out as a
 * block of their own.
 */
#[Test]
#[RunInFiber(Schedule::RoundRobin)]
#[Group('sandbox', 'async')]
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
     * Data sets at a steady pace, interleaving with the ones below.
     */
    #[DataSet(['slow-set-a'])]
    #[DataSet(['slow-set-b'])]
    public function slowDataSets(string $label): void
    {
        self::workThenYield('steady', $label);
    }

    /**
     * Data sets at a quick pace, interleaving with the ones above.
     */
    #[DataSet(['fast-set-a'])]
    #[DataSet(['fast-set-b'])]
    #[DataSet(['fast-set-c'])]
    #[DataSet(['fast-set-d'])]
    #[DataSet(['fast-set-e'])]
    #[DataSet(['fast-set-f'])]
    #[DataSet(['fast-set-g'])]
    #[DataSet(['fast-set-h'])]
    #[DataSet(['fast-set-i'])]
    #[DataSet(['fast-set-j'])]
    #[DataSet(['fast-set-k'])]
    #[DataSet(['fast-set-l'])]
    #[DataSet(['fast-set-m'])]
    #[DataSet(['fast-set-n'])]
    public function fastDataSets(string $label): void
    {
        self::workThenYield('quick', $label);
    }

    /**
     * Do the `$label` profile's steps of "work" (a nap plus an echoed progress line), suspending the
     * fiber after each one so the sibling tests take their step in between.
     *
     * @param non-empty-string $label Work profile to follow.
     * @param non-empty-string|null $tag What to print the progress under, when it is not the profile
     *        itself — a data set naming itself rather than the pace it keeps.
     */
    private static function workThenYield(string $label, ?string $tag = null): void
    {
        ['steps' => $steps, 'nap' => $nap] = self::PROFILES[$label];
        $tag ??= $label;

        $done = 0;
        for ($i = 1; $i <= $steps; $i++) {
            \usleep($nap);
            ++$done;
            echo \sprintf("[%s] step %d/%d (worked %.2fs)\n", $tag, $i, $steps, $nap / 1_000_000);
            \Fiber::suspend();
        }

        Assert::same($done, $steps);
    }
}
