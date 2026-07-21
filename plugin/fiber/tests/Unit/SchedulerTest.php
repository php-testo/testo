<?php

declare(strict_types=1);

namespace Tests\Fiber\Unit;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Fiber\Internal\Scheduler;
use Testo\Fiber\Schedule;
use Testo\Test;

/**
 * Unit checks for the cooperative fiber scheduler driving `#[RunInFiber]`. Test fibers hand control
 * back to the scheduler by calling `\Fiber::suspend()`.
 */
#[Test]
#[Covers(Scheduler::class)]
final class SchedulerTest
{
    public function soloRunsEachFiberToCompletionInOrder(): void
    {
        $log = [];
        $make = function (string $id) use (&$log): \Fiber {
            return new \Fiber(function () use ($id, &$log): void {
                $log[] = "$id.1";
                \Fiber::suspend();
                $log[] = "$id.2";
            });
        };

        $errors = Scheduler::run([$make('a'), $make('b')], Schedule::Solo);

        Assert::same($errors, []);
        # No interleaving: 'a' finishes before 'b' starts, the suspend just resumes the same fiber.
        Assert::same($log, ['a.1', 'a.2', 'b.1', 'b.2']);
    }

    public function roundRobinInterleavesAtSuspendPoints(): void
    {
        $log = [];
        $make = function (string $id) use (&$log): \Fiber {
            return new \Fiber(function () use ($id, &$log): void {
                $log[] = "$id.1";
                \Fiber::suspend();
                $log[] = "$id.2";
            });
        };

        $errors = Scheduler::run([$make('a'), $make('b')], Schedule::RoundRobin);

        Assert::same($errors, []);
        Assert::same($log, ['a.1', 'b.1', 'a.2', 'b.2']);
    }

    public function randomRunsEveryFiberToCompletion(): void
    {
        $done = [];
        $make = function (string $id) use (&$done): \Fiber {
            return new \Fiber(function () use ($id, &$done): void {
                \Fiber::suspend();
                $done[] = $id;
            });
        };

        $errors = Scheduler::run([$make('a'), $make('b'), $make('c')], Schedule::Random);
        \sort($done);

        Assert::same($errors, []);
        Assert::same($done, ['a', 'b', 'c']);
    }

    public function fiberThrowIsCapturedByIndex(): void
    {
        $ok = new \Fiber(static fn() => null);
        $bad = new \Fiber(static fn() => throw new \RuntimeException('boom'));

        $errors = Scheduler::run([$ok, $bad], Schedule::RoundRobin);

        Assert::same(\array_keys($errors), [1]);
        Assert::instanceOf($errors[1], \RuntimeException::class);
    }

    public function activeIsFalseOutsideARun(): void
    {
        # Scheduler::active() gates the interceptor's pass-through; it must be false when nothing runs.
        Assert::false(Scheduler::active());
    }
}
