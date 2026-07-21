<?php

declare(strict_types=1);

namespace Testo\Async;

use Testo\Async\Internal\Scheduler;

/**
 * Cooperative-scheduling helpers for tests running under a {@see Concurrent} case.
 *
 * @api
 */
final class Coroutine
{
    /**
     * Yield control to the concurrent scheduler so another test of the same {@see Concurrent}
     * case may take a turn, then resume here later.
     *
     * A no-op outside a `#[Concurrent]` interleaving run (a plain test, or a `#[RunInCoroutine]` test
     * on the Revolt loop), so it is safe to sprinkle wherever a context switch should be *allowed* — e.g.
     * between operations on shared state you want to stress for order-dependent races. Switching is
     * cooperative: the scheduler can only interleave tests at these points.
     */
    public static function reschedule(): void
    {
        Scheduler::active() && \Fiber::getCurrent() !== null and \Fiber::suspend(null);
    }

    private function __construct() {}
}
