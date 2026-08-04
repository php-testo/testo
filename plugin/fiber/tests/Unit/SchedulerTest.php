<?php

declare(strict_types=1);

namespace Tests\Fiber\Unit;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Fiber\Exception\CancelledException;
use Testo\Fiber\Internal\Scheduler;
use Testo\Fiber\Schedule;
use Testo\Test;

/**
 * Unit checks for the cooperative fiber scheduler driving `#[RunInFiber]` scopes. Tasks hand control
 * back to the scheduler by calling `\Fiber::suspend()`.
 */
#[Test]
#[Covers(Scheduler::class)]
final class SchedulerTest
{
    public function soloRunsEachTaskToCompletionInOrder(): void
    {
        $log = [];
        $scheduler = new Scheduler(Schedule::Solo);
        $scheduler->spawn($this->logger('a', $log));
        $scheduler->spawn($this->logger('b', $log));

        $scheduler->drive();

        # No interleaving: 'a' finishes before 'b' starts, the suspend just resumes the same fiber.
        Assert::same($log, ['a.1', 'a.2', 'b.1', 'b.2']);
    }

    public function roundRobinInterleavesAtSuspendPoints(): void
    {
        $log = [];
        $scheduler = new Scheduler(Schedule::RoundRobin);
        $scheduler->spawn($this->logger('a', $log));
        $scheduler->spawn($this->logger('b', $log));

        $scheduler->drive();

        Assert::same($log, ['a.1', 'b.1', 'a.2', 'b.2']);
    }

    public function randomRunsEveryTaskToCompletion(): void
    {
        $done = [];
        $make = static function (string $id) use (&$done): \Closure {
            return static function () use ($id, &$done): void {
                \Fiber::suspend();
                $done[] = $id;
            };
        };

        $scheduler = new Scheduler(Schedule::Random);
        $scheduler->spawn($make('a'));
        $scheduler->spawn($make('b'));
        $scheduler->spawn($make('c'));

        $scheduler->drive();
        \sort($done);

        Assert::same($done, ['a', 'b', 'c']);
    }

    public function taskThrowIsRecordedOnTheTask(): void
    {
        $scheduler = new Scheduler();
        $ok = $scheduler->spawn(static fn(): string => 'fine');
        $bad = $scheduler->spawn(static fn() => throw new \RuntimeException('boom'));

        $scheduler->drive();

        Assert::null($ok->error);
        Assert::same($ok->result, 'fine');
        Assert::instanceOf($bad->error, \RuntimeException::class);
        Assert::true($bad->finished);
    }

    public function spawnDuringTheDriveJoinsTheCurrentRound(): void
    {
        $log = [];
        $scheduler = new Scheduler();
        $scheduler->spawn(function () use (&$log, $scheduler): void {
            $log[] = 'parent.1';
            $scheduler->spawn(static function () use (&$log): void {
                $log[] = 'child.1';
                \Fiber::suspend();
                $log[] = 'child.2';
            });
            \Fiber::suspend();
            $log[] = 'parent.2';
        });

        $scheduler->drive();

        # The child got its first step in the round it was spawned, not a round later.
        Assert::same($log, ['parent.1', 'child.1', 'parent.2', 'child.2']);
    }

    public function currentPointsToTheDrivingSchedulerInsideATask(): void
    {
        Assert::null(Scheduler::current());

        $seen = null;
        $scheduler = new Scheduler();
        $scheduler->spawn(static function () use (&$seen): void {
            $seen = Scheduler::current();
        });

        $scheduler->drive();

        Assert::same($seen, $scheduler);
        Assert::null(Scheduler::current());
    }

    public function relaysToTheParentFiberBetweenRounds(): void
    {
        $scheduler = new Scheduler();
        $task = $scheduler->spawn(static function (): void {
            \Fiber::suspend();
        });

        $outer = new \Fiber(static fn() => $scheduler->drive());
        $outer->start();

        # Round 1 stepped the task (it suspended); the scheduler relayed instead of spinning.
        Assert::false($outer->isTerminated());
        Assert::false($task->finished);

        $outer->resume();

        Assert::true($outer->isTerminated());
        Assert::true($task->finished);
    }

    public function primaryFailureCancelsPendingTasks(): void
    {
        $log = [];
        $scheduler = new Scheduler();
        $body = $scheduler->spawn(static function (): void {
            \Fiber::suspend();
            throw new \RuntimeException('body died');
        });
        $child = $scheduler->spawn(static function () use (&$log): void {
            try {
                \Fiber::suspend();
                $log[] = 'unreachable';
            } finally {
                $log[] = 'cleanup';
            }
        });

        $scheduler->drive($body);

        Assert::instanceOf($body->error, \RuntimeException::class);
        Assert::true($child->finished);
        # The child was unwound by the cancellation: its finally ran, no error recorded.
        Assert::same($log, ['cleanup']);
        Assert::null($child->error);
    }

    public function swallowedCancellationIsDrivenToTermination(): void
    {
        $log = [];
        $scheduler = new Scheduler();
        $body = $scheduler->spawn(static function (): void {
            \Fiber::suspend();
            throw new \RuntimeException('body died');
        });
        $child = $scheduler->spawn(static function () use (&$log): void {
            try {
                \Fiber::suspend();
            } catch (CancelledException) {
                $log[] = 'caught';
            }
            $log[] = 'after';
        });

        $scheduler->drive($body);

        Assert::true($child->finished);
        Assert::same($log, ['caught', 'after']);
    }

    public function rejectsAStartedFiber(): void
    {
        $fiber = new \Fiber(static fn() => \Fiber::suspend());
        $fiber->start();

        $scheduler = new Scheduler();

        $caught = null;
        try {
            $scheduler->spawn($fiber);
        } catch (\LogicException $e) {
            $caught = $e;
        }

        Assert::notNull($caught);
    }

    /**
     * @param list<string> $log
     */
    private function logger(string $id, array &$log): \Closure
    {
        return static function () use ($id, &$log): void {
            $log[] = "$id.1";
            \Fiber::suspend();
            $log[] = "$id.2";
        };
    }
}
