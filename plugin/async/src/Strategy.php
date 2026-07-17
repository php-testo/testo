<?php

declare(strict_types=1);

namespace Testo\Async;

/**
 * Scheduling strategy for a {@see \Testo\Concurrent} test case — the order in which the case's tests
 * are resumed at cooperative suspension points.
 *
 * @api
 */
enum Strategy
{
    /**
     * Run each test to completion before starting the next, all within one shared loop run.
     */
    case Sequential;

    /**
     * Give each runnable test one step per round at its cooperative suspension points.
     *
     * @todo Not yet implemented; requires a cooperative reschedule primitive.
     */
    case RoundRobin;

    /**
     * Resume a random runnable test at each cooperative suspension point, to shake out
     * order-dependent races.
     *
     * @todo Not yet implemented; requires a cooperative reschedule primitive.
     */
    case Random;
}
