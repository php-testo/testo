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
     * Run each test to completion, one at a time — no interleaving.
     *
     * Each test runs alone in its own fiber, driven to completion before the next one starts — no
     * other test interleaves it. The default.
     */
    case Solo;

    /**
     * Run all tests together, giving each fiber a turn in order.
     *
     * Interleave the case's tests: one step per ready test each round, in order, switching wherever a
     * test's fiber calls `\Fiber::suspend()`.
     */
    case RoundRobin;

    /**
     * Run all tests together, switching fibers in random order.
     *
     * Interleave the case's tests by stepping a random ready test each round, to shake out
     * order-dependent races. Non-seeded for now, so runs are not reproducible.
     */
    case Random;
}
