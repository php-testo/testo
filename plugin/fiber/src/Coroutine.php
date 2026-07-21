<?php

declare(strict_types=1);

namespace Testo\Fiber;

use Testo\Fiber\Internal\Scheduler;

/**
 * Cooperative-scheduling helpers for tests running under a {@see RunInFiber} case.
 *
 * @api
 */
final class Coroutine
{
    /**
     * Yield control to the fiber scheduler so another test of the same {@see RunInFiber} case may take
     * a turn, then resume here later.
     *
     * A no-op outside an interleaving run (a plain test, or `Schedule::Solo`), so it is safe to
     * sprinkle wherever a context switch should be *allowed* — e.g. between operations on shared state
     * you want to stress for order-dependent races. Switching is cooperative: the scheduler can only
     * interleave tests at these points.
     */
    public static function reschedule(): void
    {
        Scheduler::active() && \Fiber::getCurrent() !== null and \Fiber::suspend(null);
    }

    private function __construct() {}
}
