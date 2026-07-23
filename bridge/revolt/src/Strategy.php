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
     * {@see \Testo\Core\Context\CaseInfo::$batchRunner}, so every test in the case shares one loop run.
     *
     * This lets a future scheduler interleave the case's tests on the shared loop, but it puts the
     * per-test pipeline — including the fiber-aware guards — inside a loop fiber, which the guards
     * cannot yet cooperate with (they hand-drive their own fiber and deadlock the Revolt driver).
     * **Currently deadlocks**; usable once the guards move to fiber-local storage.
     */
    case PerCase;
}
