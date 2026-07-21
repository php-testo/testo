<?php

declare(strict_types=1);

namespace Tests\Fiber\Unit;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Fiber\Coroutine;
use Testo\Fiber\Internal\Scheduler;
use Testo\Fiber\Schedule;
use Testo\Test;

/**
 * Unit checks for the cooperative fiber scheduler driving `#[RunInFiber]`.
 */
#[Test]
#[Covers(Scheduler::class)]
#[Covers(Coroutine::class)]
final class SchedulerTest
{
    public function oneByOneRunsEachFiberToCompletionInOrder(): void
    {
        $log = [];
        $make = function (string $id) use (&$log): \Fiber {
            return new \Fiber(function () use ($id, &$log): void {
                $log[] = "$id.1";
                Coroutine::reschedule();
                $log[] = "$id.2";
            });
        };

        $errors = Scheduler::run([$make('a'), $make('b')], Schedule::OneByOne);

        Assert::same($errors, []);
        # No interleaving: 'a' finishes before 'b' starts, reschedule() just resumes the same fiber.
        Assert::same($log, ['a.1', 'a.2', 'b.1', 'b.2']);
    }

    public function roundRobinInterleavesAtReschedulePoints(): void
    {
        $log = [];
        $make = function (string $id) use (&$log): \Fiber {
            return new \Fiber(function () use ($id, &$log): void {
                $log[] = "$id.1";
                Coroutine::reschedule();
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
                Coroutine::reschedule();
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

    public function rescheduleIsInactiveOutsideTheScheduler(): void
    {
        # Outside Scheduler::run there is no active scheduler, so reschedule() must be a no-op even
        # inside a fiber (it must not suspend and strand the caller).
        $fiber = new \Fiber(static function (): string {
            Coroutine::reschedule();
            return 'done';
        });
        $fiber->start();

        Assert::false(Scheduler::active());
        Assert::true($fiber->isTerminated());
        Assert::same($fiber->getReturn(), 'done');
    }
}
