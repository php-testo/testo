<?php

declare(strict_types=1);

namespace Testo\Fiber;

/**
 * How the tests of a {@see RunInFiber} case are scheduled on Testo's cooperative fiber scheduler —
 * i.e. in what order the test fibers get their turn.
 *
 * @api
 */
enum Schedule
{
    /**
     * Each test runs in its own fiber, driven to completion before the next one starts. No
     * interleaving — the default.
     */
    case OneByOne;

    /**
     * Interleave the case's tests: one step per ready test each round, in order, switching at
     * {@see Coroutine::reschedule()} points.
     */
    case RoundRobin;

    /**
     * Interleave the case's tests by stepping a random ready test each round, to shake out
     * order-dependent races. Non-seeded for now, so runs are not reproducible.
     */
    case Random;
}
