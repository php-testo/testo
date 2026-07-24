<?php

declare(strict_types=1);

namespace Testo\Bridge\Revolt;

/**
 * When {@see RunInRevolt} enters the Revolt event loop for a case's tests.
 *
 * @api
 */
enum Strategy
{
    /**
     * Enter the loop **per test**: each test's whole pipeline is placed on the loop individually, one test
     * at a time, each run to completion before the next. The safe default — no interleaving, so tests
     * never observe each other's scoped state.
     */
    case PerTest;

    /**
     * Enter the loop **per case**: the whole case is driven on the loop through
     * {@see \Testo\Core\Context\CaseInfo::$batchRunner}, which launches every test as its own coroutine
     * **at once** so they run concurrently, interleaving at their await points.
     *
     * The per-test pipeline — including Testo's fiber-aware scoped-state guards — runs inside a loop fiber.
     * The guards hold their state per fiber (see {@see \Testo\Common\FiberLocal}), so each test reads its
     * own assertion/messenger state even while several tests interleave on one loop.
     */
    case PerCase;
}
