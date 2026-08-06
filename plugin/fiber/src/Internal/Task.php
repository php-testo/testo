<?php

declare(strict_types=1);

namespace Testo\Fiber\Internal;

/**
 * A single coroutine scheduled on a {@see Scheduler}: the fiber plus its lifecycle state.
 *
 * @internal
 * @psalm-internal Testo\Fiber
 */
final class Task
{
    /**
     * The task will not be stepped anymore: its fiber terminated, or the scope cancelled it.
     */
    public bool $finished = false;

    /**
     * Return value of the fiber once it finished without an error.
     */
    public mixed $result = null;

    /**
     * The throwable that terminated the fiber, if any.
     */
    public ?\Throwable $error = null;

    /**
     * The error was rethrown to an awaiter, so the scope must not surface it again on close.
     */
    public bool $errorObserved = false;

    /**
     * The scope cancelled the task while it was still pending — it has no result to report, even if
     * its fiber swallowed the cancellation and terminated on its own terms.
     */
    public bool $cancelled = false;

    /**
     * The task this one is parked on (inside {@see \Testo\Fiber\Coroutine::await()}) —
     * not ready while the target is unfinished.
     */
    public ?Task $awaiting = null;

    public function __construct(
        public readonly \Fiber $fiber,
        public readonly Scheduler $scheduler,
        public readonly int $id,
    ) {}
}
