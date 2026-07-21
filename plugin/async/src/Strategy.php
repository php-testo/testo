<?php

declare(strict_types=1);

namespace Testo\Async;

/**
 * Scheduling strategy for a {@see Concurrent} test case — the order in which the case's tests
 * are resumed at cooperative suspension points.
 *
 * @api
 */
enum Strategy
{
    /**
     * Run each test to completion before starting the next (Testo's default order) — a pass-through.
     */
    case Sequential;

    /**
     * Interleave the case's tests: one step per ready test each round, in order, switching at
     * {@see \Testo\Async\Coroutine::reschedule()} points.
     */
    case RoundRobin;

    /**
     * Interleave the case's tests by stepping a random ready test each round, to shake out
     * order-dependent races. Non-seeded for now, so runs are not reproducible.
     */
    case Random;
}
