<?php

declare(strict_types=1);

namespace Testo\Fiber\Exception;

/**
 * Every pending coroutine of a scope is parked on an {@see \Testo\Fiber\Coroutine::await()} that can
 * never complete — an await cycle.
 *
 * The scheduler breaks the cycle by raising this at the first parked coroutine's `await()` call, so
 * the stack trace points at the guilty wait; the failure then cascades to the coroutines awaiting it.
 * A bare `\Fiber::suspend()` loop waiting for something that never happens is **not** detected — only
 * `await()` parks a coroutine in a way the scheduler can reason about.
 *
 * @api
 */
final class DeadlockException extends \RuntimeException {}
