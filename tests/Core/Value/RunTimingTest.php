<?php

declare(strict_types=1);

namespace Tests\Core\Value;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Value\RunTiming;
use Testo\Test;

#[Test]
#[Covers(RunTiming::class)]
final class RunTimingTest
{
    public function everyPhaseIsZeroByDefault(): void
    {
        $timing = new RunTiming();

        Assert::same($timing->startup, 0.0);
        Assert::same($timing->discovery, 0.0);
        Assert::same($timing->tests, 0.0);
        Assert::same($timing->teardown, 0.0);
        Assert::same($timing->duration(), 0.0);
        Assert::same($timing->total(), 0.0);
    }

    public function durationIsTheLoopWhileTotalIsEveryPhase(): void
    {
        // Binary-exact fractions so the sums compare with `same()` and not a float epsilon.
        $timing = new RunTiming(startup: 0.25, discovery: 0.5, tests: 1.0, teardown: 0.25);

        // The loop is discovery + execution; startup and teardown sit outside it.
        Assert::same($timing->duration(), 1.5);
        Assert::same($timing->total(), 2.0);
    }

    public function phasesAreKeyedInTheOrderTheyBegan(): void
    {
        $timing = new RunTiming(startup: 1.0, discovery: 2.0, tests: 3.0, teardown: 4.0);

        Assert::same($timing->phases(), [
            'startup' => 1.0,
            'discovery' => 2.0,
            'tests' => 3.0,
            'teardown' => 4.0,
        ]);
    }
}
