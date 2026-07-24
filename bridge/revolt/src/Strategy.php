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
     * Enter the loop **per test**: each test is placed on the loop individually, from *inside* Testo's
     * fiber-aware scoped-state guards (the guards stay on their synchronous main-fiber path, only the
     * test body reaches the loop). This is the safe default and works today.
     */
    case PerTest;

    /**
     * Enter the loop **per case**: the whole case is driven on the loop through
     * {@see \Testo\Core\Context\CaseInfo::$batchRunner}, which launches every test as its own coroutine
     * **at once** so they run concurrently, interleaving at their await points.
     *
     * This puts the per-test pipeline — including the fiber-aware guards — inside a loop fiber, which
     * the guards cannot yet cooperate with (they hand-drive their own fiber and deadlock the Revolt
     * driver, and concurrent tests clash the shared assertion state). So with the current guards
     * **PerCase deadlocks / fails**; it becomes correct once the guards move to fiber-local storage
     * (a change made on the main branch, not on this bridge).
     */
    case PerCase;
}
