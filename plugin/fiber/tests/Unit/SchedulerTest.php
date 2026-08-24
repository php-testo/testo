<?php

declare(strict_types=1);

namespace Tests\Fiber\Unit;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Fiber\Coroutine;
use Testo\Fiber\Exception\CancelledException;
use Testo\Fiber\Exception\DeadlockException;
use Testo\Fiber\Internal\Scheduler;
use Testo\Fiber\Internal\Task;
use Testo\Fiber\Schedule;
use Testo\Filter\Group;
use Testo\Test;

/**
 * Unit checks for the cooperative fiber scheduler driving `#[RunInFiber]` scopes. Tasks hand control
 * back to the scheduler by calling `\Fiber::suspend()`.
 */
#[Test]
#[Group('async')]
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

    public function primaryFailedPredicateCancelsPendingTasks(): void
    {
        $log = [];
        $scheduler = new Scheduler();
        $body = $scheduler->spawn(static function (): string {
            \Fiber::suspend();

            return 'captured failure';
        });
        $child = $scheduler->spawn(static function () use (&$log): void {
            try {
                \Fiber::suspend();
                $log[] = 'survived';
            } catch (CancelledException) {
                $log[] = 'cancelled';
            }
        });

        # The primary settles without an error — the predicate is what recognizes the failure.
        $scheduler->drive($body, static fn(Task $task): bool => $task->result === 'captured failure');

        Assert::null($body->error);
        Assert::true($child->finished);
        Assert::same($log, ['cancelled']);
    }

    public function spawnWhileTheScopeIsClosingThrows(): void
    {
        $scheduler = new Scheduler();
        $body = $scheduler->spawn(static function (): void {
            \Fiber::suspend();
            throw new \RuntimeException('body died');
        });
        $child = $scheduler->spawn(static function () use ($scheduler): void {
            try {
                \Fiber::suspend();
            } finally {
                $scheduler->spawn(static fn(): string => 'cleanup nobody will ever drive');
            }
        });

        $scheduler->drive($body);

        # The late spawn was rejected loudly, not silently added to a schedule nobody drives anymore.
        Assert::instanceOf($child->error, \LogicException::class);
        Assert::string($child->error->getMessage())->contains('closing');
        Assert::same(\count($scheduler->tasks()), 2);
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

    /**
     * Two scopes driven by an outer schedule, each with a coroutine awaiting the other scope's
     * coroutine through shared handles — an await cycle spanning schedulers. Neither scope may spin
     * relaying forever: the cycle must be detected and broken like a local one. The outer loop is
     * bounded so a livelock fails the test instead of hanging it.
     */
    public function crossSchedulerAwaitCycleIsBrokenAsADeadlock(): void
    {
        $handleA = $handleB = null;

        $scopeA = new Scheduler();
        $bodyA = $scopeA->spawn(static function () use (&$handleA, &$handleB): mixed {
            $handleA = Coroutine::spawn(static function () use (&$handleB): mixed {
                while ($handleB === null) {
                    \Fiber::suspend();
                }

                return $handleB->await();
            });

            return $handleA->await();
        });

        $scopeB = new Scheduler();
        $bodyB = $scopeB->spawn(static function () use (&$handleA, &$handleB): mixed {
            $handleB = Coroutine::spawn(static fn(): mixed => $handleA->await());

            return $handleB->await();
        });

        $fiberA = new \Fiber(static fn() => $scopeA->drive($bodyA));
        $fiberB = new \Fiber(static fn() => $scopeB->drive($bodyB));

        for ($i = 0; $i < 100 && !($fiberA->isTerminated() && $fiberB->isTerminated()); $i++) {
            $fiberA->isTerminated() or ($fiberA->isStarted() ? $fiberA->resume() : $fiberA->start());
            $fiberB->isTerminated() or ($fiberB->isStarted() ? $fiberB->resume() : $fiberB->start());
        }

        Assert::true(
            $fiberA->isTerminated() && $fiberB->isTerminated(),
            'The cross-scheduler await cycle was never broken — the scopes relay forever.',
        );

        # Both bodies failed, and the deadlock is the root of the cascade in at least one of them.
        Assert::notNull($bodyA->error);
        Assert::notNull($bodyB->error);
        $deadlocked = false;
        foreach ([$bodyA->error, $bodyB->error] as $error) {
            for (; $error !== null; $error = $error->getPrevious()) {
                $error instanceof DeadlockException and $deadlocked = true;
            }
        }
        Assert::true($deadlocked);
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
