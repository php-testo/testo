<?php

declare(strict_types=1);

namespace Testo\Fiber;

use Testo\Fiber\Exception\CancelledException;
use Testo\Fiber\Exception\CompositeException;
use Testo\Fiber\Internal\Scheduler;
use Testo\Fiber\Internal\Task;

/**
 * A handle to a coroutine spawned into the running test's cooperative scope.
 *
 * Every {@see RunInFiber} test runs inside its own coroutine scope: the test body is the scope's
 * first coroutine, and {@see spawn()} adds more to the same schedule. Coroutines interleave with the
 * test body (and each other) wherever a fiber calls `\Fiber::suspend()`, and — under a class-level
 * `#[RunInFiber]` — the whole scope keeps interleaving with the case's other tests. Assertions and
 * messages inside a coroutine are attributed to the test that spawned it.
 *
 * ```php
 *  #[RunInFiber]
 *  public function pingPong(): void
 *  {
 *      $server = Coroutine::spawn(fn(): string => $this->acceptAndEcho());
 *      $client = Coroutine::spawn(fn(): string => $this->connectAndSend('ping'));
 *
 *      Assert::same($client->await(), 'pong');
 *      Assert::same($server->await(), 'ping');
 *  }
 * ```
 *
 * The scope is structured: the test is not finished until every coroutine it spawned is. A coroutine
 * still pending when the test body returns keeps being driven; if the body fails, pending coroutines
 * are cancelled ({@see CancelledException} is thrown into them). Coroutine failures are always
 * surfaced wrapped in a {@see CompositeException} — even a single one — whether rethrown by
 * {@see await()} / {@see concurrently()} or reported by the scope for a coroutine nobody awaited.
 *
 * @api
 */
final readonly class Coroutine
{
    private function __construct(
        private Task $task,
    ) {}

    /**
     * Schedule a closure (or an unstarted fiber) as a coroutine of the running test's scope.
     *
     * The coroutine gets its first step in the current scheduling round; from there it runs
     * cooperatively — it holds the floor until it suspends, finishes, or awaits.
     *
     * @throws \LogicException When no coroutine scope is active — run the test with `#[RunInFiber]` —
     *         or when the scope is already closing (spawning from a cancelled coroutine's `finally`).
     */
    public static function spawn(\Closure|\Fiber $body): self
    {
        $scheduler = Scheduler::current() ?? throw new \LogicException(
            'No active coroutine scope — run the test with #[RunInFiber] to use Coroutine::spawn().',
        );

        return new self($scheduler->spawn($body));
    }

    /**
     * Run the given closures/fibers concurrently and wait for all of them.
     *
     * Sugar over {@see spawn()} + {@see await()}: schedules everything into the running scope, parks
     * the caller until every coroutine finished, and returns the results keyed like the arguments
     * (named arguments give string keys). Failures are collected until all coroutines settle, then
     * bundled into one {@see CompositeException} — its errors keyed like the arguments too,
     * symmetric to the results. A coroutine that itself died with a `CompositeException` appears
     * nested: that whole exception sits under the argument's key, its own structure intact.
     *
     * @return array<array-key, mixed> Results keyed like the arguments.
     *
     * @throws CompositeException When any of the coroutines threw — errors keyed like the arguments.
     * @throws \LogicException When no coroutine scope is active — run the test with `#[RunInFiber]`.
     */
    public static function concurrently(\Closure|\Fiber ...$bodies): array
    {
        $handles = \array_map(self::spawn(...), $bodies);

        $results = $errors = [];
        foreach ($handles as $key => $handle) {
            try {
                $results[$key] = $handle->await();
            } catch (CompositeException $e) {
                // await() wraps exactly one task's error — unwrap and re-key it by the argument,
                // so callers never see the scheduler's internal task ids.
                $errors[$key] = $e->errors[\array_key_first($e->errors)];
            }
        }

        $errors === [] or throw new CompositeException($errors);

        return $results;
    }

    /**
     * Whether the coroutine has settled — returned, thrown, or been cancelled.
     */
    public function isFinished(): bool
    {
        return $this->task->finished;
    }

    /**
     * Park the calling coroutine until this one finishes, and return its result.
     *
     * Other coroutines keep running while the caller is parked. Rethrowing a failure here marks it
     * as observed, so the scope will not report it again. A cancellation is rethrown unwrapped — it
     * is the scope's control signal, not a failure of the coroutine.
     *
     * @throws CompositeException When the awaited coroutine threw.
     * @throws CancelledException When the awaited coroutine was cancelled with its scope.
     * @throws \LogicException When called outside a coroutine scope, or when a coroutine awaits itself.
     */
    public function await(): mixed
    {
        while (!$this->task->finished) {
            $caller = Scheduler::current()?->runningTask() ?? throw new \LogicException(
                'Coroutine::await() on a pending coroutine must be called from inside a coroutine scope.',
            );
            $caller === $this->task and throw new \LogicException('A coroutine cannot await itself.');

            $caller->awaiting = $this->task;
            try {
                \Fiber::suspend();
            } finally {
                $caller->awaiting = null;
            }
        }

        if ($this->task->error !== null) {
            $this->task->errorObserved = true;
            throw new CompositeException([$this->task->id => $this->task->error]);
        }

        $this->task->cancelled and throw new CancelledException('The awaited coroutine was cancelled with its scope.');

        return $this->task->result;
    }
}
