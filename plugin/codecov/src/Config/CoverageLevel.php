<?php

declare(strict_types=1);

namespace Testo\Codecov\Config;

/**
 * Defines the depth of code coverage analysis.
 *
 * Each level includes all data from the previous one:
 * - **Line** — which lines of code were executed during tests.
 * - **Branch** — Line + which branches (if/else, switch cases, ternary, ??) were taken.
 * - **Path** — Branch + which complete execution paths through each function were followed.
 *
 * Higher levels provide deeper insight but increase overhead.
 *
 * ## Example
 *
 * Given the code:
 *
 * ```
 *  function greet(bool $loud, bool $formal): string
 *  {
 *      $greeting = $formal ? 'Good day' : 'Hi';    // 2 branches: true/false
 *      return $loud ? strtoupper($greeting) : $greeting; // 2 branches: true/false
 *  }
 * ```
 *
 * - **Line** reports whether each line was executed (e.g. lines 3 and 4).
 * - **Branch** reports which branches were taken:
 *   line 3 has 2 branches (`$formal` true or false),
 *   line 4 has 2 branches (`$loud` true or false) — 4 branches total.
 *   A test calling `greet(true, true)` covers 2 of 4 branches (50%).
 * - **Path** reports which *combinations* of branches were followed:
 *   there are 4 possible paths (true+true, true+false, false+true, false+false).
 *   A test calling `greet(true, true)` covers 1 of 4 paths (25%).
 *
 * ## Driver support
 *
 * - **PCOV** — only supports {@see Line}. Higher levels silently fall back to Line.
 * - **XDebug** — supports all three levels. Branch and Path require XDebug's
 *   `XDEBUG_CC_BRANCH_CHECK` flag, which adds analysis overhead.
 *
 * Collecting {@see Branch} or {@see Path} inside a fiber (a test under `#[RunInFiber]`) needs XDebug
 * 3.4.5 or newer; older builds corrupt memory and kill the process, so the run is stopped with
 * {@see \Testo\Codecov\Exception\BranchCoverageUnsafeInFiber} instead.
 *
 * @api
 */
enum CoverageLevel
{
    /**
     * Track which lines were executed. Supported by both PCOV and XDebug.
     */
    case Line;

    /**
     * Track which branches were taken. XDebug only.
     *
     * A branch is a possible direction at a decision point: each side of an `if/else`,
     * each `case` in a `switch`, each arm of a `match`, the true/false side of `?:`, etc.
     *
     * Includes all line coverage data.
     */
    case Branch;

    /**
     * Track which execution paths were followed. XDebug only.
     *
     * A path is a unique combination of branches through a function from entry to exit.
     * A function with two independent `if` statements has 4 possible paths (2 × 2).
     * Path coverage reveals untested combinations that branch coverage alone may miss.
     *
     * Includes all line and branch coverage data.
     */
    case Path;
}
