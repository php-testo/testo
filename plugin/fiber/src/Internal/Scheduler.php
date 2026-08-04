<?php

declare(strict_types=1);

namespace Testo\Fiber\Internal;

use Testo\Fiber\Exception\CancelledException;
use Testo\Fiber\Exception\DeadlockException;
use Testo\Fiber\Schedule;

/**
 * Cooperative fiber scheduler for {@see \Testo\Fiber\RunInFiber} and {@see \Testo\Fiber\Coroutine}.
 *
 * Drives a dynamic set of tasks to completion on **plain fibers** (no event loop), switching between
 * them only where the running fiber calls `\Fiber::suspend()`. Tasks may be spawned while the
 * scheduler is driving — under {@see Schedule::RoundRobin} they join the current round.
 *
 * When the scheduler itself runs inside a fiber (a test's coroutine scope under a case-level
 * scheduler), it relays control upward after every round with a `\Fiber::suspend()` of its own — so
 * its tasks keep interleaving with the outer schedule, and Testo's fiber-aware guards swap the
 * scoped per-test state in and out at each relay. Tasks are only ever resumed from inside their own
 * scheduler's drive frame, which is why coroutines always observe the state of the test that
 * spawned them.
 *
 * @internal
 * @psalm-internal Testo\Fiber
 */
final class Scheduler
{
    /**
     * The scheduler owning the innermost task that is currently running.
     */
    private static ?self $current = null;

    /** @var array<int, Task> */
    private array $tasks = [];

    private int $nextId = 0;

    private ?Task $running = null;

    public function __construct(
        private readonly Schedule $schedule = Schedule::RoundRobin,
    ) {}

    /**
     * The scheduler whose task is currently running, if any. This is where the {@see \Testo\Fiber\Coroutine}
     * helpers land: user code always runs inside a task, so the ambient scheduler is its scope.
     */
    public static function current(): ?self
    {
        return self::$current;
    }

    /**
     * The task this scheduler is currently stepping.
     */
    public function runningTask(): ?Task
    {
        return $this->running;
    }

    /**
     * @return array<int, Task> All scheduled tasks keyed by id, in spawn order.
     */
    public function tasks(): array
    {
        return $this->tasks;
    }

    /**
     * Add a task to the schedule. May be called while the scheduler is driving.
     *
     * @param \Closure|\Fiber $body An unstarted fiber, or a closure to wrap into one.
     */
    public function spawn(\Closure|\Fiber $body): Task
    {
        $fiber = $body instanceof \Fiber ? $body : new \Fiber($body);
        $fiber->isStarted() and throw new \LogicException('Cannot schedule a fiber that has already been started.');

        $id = $this->nextId++;
        return $this->tasks[$id] = new Task($fiber, $this, $id);
    }

    /**
     * Drive the scheduled tasks to completion.
     *
     * A round steps every ready task once ({@see Schedule::RoundRobin}), or a single ready task
     * ({@see Schedule::Solo} — always the first, so it runs to completion before the next starts;
     * {@see Schedule::Random} — a random one). A task parked on an await is not ready until the
     * awaited task finishes. Between rounds, when unfinished tasks remain and the scheduler runs
     * inside a fiber, control is relayed to the parent scheduler.
     *
     * With `$primary` set (a test's coroutine scope, where `$primary` is the test body), a primary
     * failure cancels the remaining tasks instead of driving them further: a {@see CancelledException}
     * is thrown into every pending fiber so its `finally` blocks run; a throwable escaping that unwind
     * (other than the cancellation itself) is recorded as the task's error.
     *
     * An await cycle is broken by throwing a {@see DeadlockException} into the first parked task;
     * the failure then cascades to its awaiters, so the deadlock surfaces as an ordinary task error
     * with a stack trace pointing at the guilty `await()`.
     */
    public function drive(?Task $primary = null): void
    {
        $prev = self::$current;
        self::$current = $this;
        try {
            while (true) {
                $ready = $parked = [];
                foreach ($this->tasks as $id => $task) {
                    if ($task->finished) {
                        continue;
                    }

                    self::ready($task) ? $ready[] = $id : $parked[] = $id;
                }

                if ($ready === [] && $parked === []) {
                    return;
                }

                if ($ready === []) {
                    // Every unfinished task is parked in an await. If any of them is parked on
                    // another scheduler's task, the outer schedule may still unpark it — relay and
                    // retry. Otherwise no step can ever unpark them: an await cycle.
                    if (\Fiber::getCurrent() !== null && !$this->parkedTasksAreLocal($parked)) {
                        $this->relay($prev);
                        continue;
                    }

                    // Break the cycle: the first parked task gets the deadlock at its await point
                    // and unwinds; its awaiters unpark and the failure cascades through the cycle.
                    $this->throwInto($this->tasks[$parked[0]], new DeadlockException($this->describeDeadlock($parked)));
                    continue;
                }

                if ($this->schedule === Schedule::RoundRobin) {
                    // One step per ready task, in spawn order; tasks spawned during the round are
                    // appended to the list and get their first step in the same round.
                    for ($id = 0; $id < $this->nextId; $id++) {
                        $task = $this->tasks[$id];
                        self::ready($task) and $this->step($task);

                        if (self::failed($primary)) {
                            $this->cancelPending();
                            return;
                        }
                    }
                } else {
                    $pick = $this->schedule === Schedule::Solo
                        ? $ready[0]
                        : $ready[\random_int(0, \count($ready) - 1)];
                    $this->step($this->tasks[$pick]);

                    if (self::failed($primary)) {
                        $this->cancelPending();
                        return;
                    }
                }

                if (\Fiber::getCurrent() !== null && $this->hasUnfinished()) {
                    $this->relay($prev);
                }
            }
        } finally {
            self::$current = $prev;
        }
    }

    private static function ready(Task $task): bool
    {
        return !$task->finished && ($task->awaiting === null || $task->awaiting->finished);
    }

    private static function failed(?Task $primary): bool
    {
        return $primary !== null && $primary->finished && $primary->error !== null;
    }

    /**
     * Give the running fiber a step: resume it (or start it), and record how it ended.
     */
    private function step(Task $task): void
    {
        $previous = $this->running;
        $this->running = $task;
        try {
            $task->fiber->isStarted() ? $task->fiber->resume() : $task->fiber->start();
        } catch (\Throwable $e) {
            $task->error = $e;
        } finally {
            $this->running = $previous;
            $this->settle($task);
        }
    }

    /**
     * Raise `$e` inside the task's fiber at its current suspension point.
     */
    private function throwInto(Task $task, \Throwable $e): void
    {
        $previous = $this->running;
        $this->running = $task;
        try {
            $task->fiber->throw($e);
        } catch (\Throwable $err) {
            $task->error = $err;
        } finally {
            $this->running = $previous;
            $this->settle($task);
        }
    }

    private function settle(Task $task): void
    {
        if (!$task->fiber->isTerminated()) {
            return;
        }

        $task->finished = true;
        $task->error === null and $task->result = $task->fiber->getReturn();
    }

    /**
     * Hand control to the parent scheduler until it steps us again.
     */
    private function relay(?self $prev): void
    {
        self::$current = $prev;
        try {
            \Fiber::suspend();
        } finally {
            self::$current = $this;
        }
    }

    private function hasUnfinished(): bool
    {
        foreach ($this->tasks as $task) {
            if (!$task->finished) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param non-empty-list<int> $parked
     */
    private function parkedTasksAreLocal(array $parked): bool
    {
        foreach ($parked as $id) {
            if ($this->tasks[$id]->awaiting?->scheduler !== $this) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cancel every pending task: mark them all finished first (so an unwinding task that awaits a
     * sibling sees it settled instead of parking forever), then throw a {@see CancelledException}
     * into each started fiber so its `finally` blocks run. A fiber that swallows the cancellation
     * and suspends again is resumed until it terminates.
     */
    private function cancelPending(): void
    {
        $pending = [];
        foreach ($this->tasks as $task) {
            if (!$task->finished) {
                $task->finished = true;
                $pending[] = $task;
            }
        }

        foreach ($pending as $task) {
            $fiber = $task->fiber;
            if (!$fiber->isStarted()) {
                continue;
            }

            try {
                $fiber->throw(new CancelledException('The coroutine scope is closing.'));
                while (!$fiber->isTerminated()) {
                    $fiber->resume();
                }
            } catch (CancelledException) {
                // Unwound cleanly.
            } catch (\Throwable $e) {
                // A real failure while unwinding — the scope will surface it.
                $task->error = $e;
            }
        }
    }

    /**
     * @param non-empty-list<int> $parked
     */
    private function describeDeadlock(array $parked): string
    {
        $lines = [];
        foreach ($parked as $id) {
            $lines[] = \sprintf('#%d awaits #%d', $id, $this->tasks[$id]->awaiting?->id ?? -1);
        }

        return \sprintf(
            'Coroutine deadlock — every pending coroutine is parked on an await that can never complete: %s.',
            \implode('; ', $lines),
        );
    }
}
