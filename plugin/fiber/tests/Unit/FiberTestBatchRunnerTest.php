<?php

declare(strict_types=1);

namespace Tests\Fiber\Unit;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\TestResult;
use Testo\Fiber\Exception\CompositeException;
use Testo\Fiber\Internal\FiberTestBatchRunner;
use Testo\Fiber\Schedule;
use Testo\Test;

/**
 * Unit: when the scheduler surfaces throwables from test fibers, the batch runner bundles them all into
 * one {@see CompositeException} instead of dropping every failure but the first.
 */
#[Test]
#[Covers(FiberTestBatchRunner::class)]
#[Covers(CompositeException::class)]
final class FiberTestBatchRunnerTest
{
    public function aggregatesEveryFiberFailureKeyedByIndex(): void
    {
        $first = new \RuntimeException('boom-0');
        $second = new \LogicException('boom-1');

        $caught = null;
        try {
            (new FiberTestBatchRunner(Schedule::RoundRobin))([
                static fn(): TestResult => throw $first,
                static fn(): TestResult => throw $second,
            ]);
        } catch (CompositeException $e) {
            $caught = $e;
        }

        Assert::notNull($caught);
        Assert::same($caught->errors, [0 => $first, 1 => $second]);
        # The earliest failure is chained so ordinary renderers still show a root cause.
        Assert::same($caught->getPrevious(), $first);
    }

    public function wrapsASingleFailureToo(): void
    {
        $only = new \RuntimeException('only');

        $caught = null;
        try {
            (new FiberTestBatchRunner(Schedule::Solo))([
                static fn(): TestResult => throw $only,
            ]);
        } catch (CompositeException $e) {
            $caught = $e;
        }

        Assert::notNull($caught);
        Assert::same($caught->errors, [0 => $only]);
        Assert::same($caught->getPrevious(), $only);
    }
}
