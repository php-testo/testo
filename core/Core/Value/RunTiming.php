<?php

declare(strict_types=1);

namespace Testo\Core\Value;

/**
 * Where a run spent its time, phase by phase.
 *
 * The phases are **accumulated durations, not intervals**: discovery and execution interleave, since a
 * suite is scanned, run, and only then is the next one scanned. So `discovery` and `tests` each sum the
 * time spent that way across every suite rather than marking a single contiguous stretch of the clock.
 *
 * Two derived totals read the same four numbers from different angles: {@see total()} is the whole run
 * end to end, {@see duration()} is only the suite loop (discovery + execution), the part bracketed by
 * `startup` before it and `teardown` after. Neither counts writing a report: that happens in a
 * {@see \Testo\Event\Framework\SessionFinished} listener, which runs after this DTO is stamped.
 *
 * @api
 */
final readonly class RunTiming
{
    /**
     * @param float $startup Bootstrap before the suite loop: application plugins, filter, session start.
     * @param float $discovery Scanning suites into runnable tests, summed across the run.
     * @param float $tests Executing the test pipelines, summed across the run.
     * @param float $teardown After the last suite, up to building the result.
     */
    public function __construct(
        public float $startup = 0.0,
        public float $discovery = 0.0,
        public float $tests = 0.0,
        public float $teardown = 0.0,
    ) {}

    /**
     * The suite loop: discovery and execution, which interleave. Excludes startup and teardown.
     */
    public function duration(): float
    {
        return $this->discovery + $this->tests;
    }

    /**
     * The whole run, wall-clock, every phase end to end.
     */
    public function total(): float
    {
        return $this->startup + $this->discovery + $this->tests + $this->teardown;
    }

    /**
     * Every phase keyed by name, in the order it began — for a reporter that lists them.
     *
     * @return array{startup: float, discovery: float, tests: float, teardown: float}
     */
    public function phases(): array
    {
        return [
            'startup' => $this->startup,
            'discovery' => $this->discovery,
            'tests' => $this->tests,
            'teardown' => $this->teardown,
        ];
    }
}
