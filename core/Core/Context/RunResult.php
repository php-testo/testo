<?php

declare(strict_types=1);

namespace Testo\Core\Context;

use Testo\Core\Value\RunTiming;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;

/**
 * Result of running tests.
 *
 * @implements \IteratorAggregate<SuiteResult>
 */
final readonly class RunResult implements \IteratorAggregate
{
    /**
     * @param iterable<SuiteResult> $results Test result collection.
     * @param Summary $summary Aggregated statistics of the session (sum of its suite summaries).
     * @param RunTiming $timing Where the run spent its time, phase by phase.
     */
    public function __construct(
        public iterable $results,
        public Status $status,
        public Summary $summary = new Summary(),
        public RunTiming $timing = new RunTiming(),
    ) {}

    /**
     * Wall-clock of the suite loop (discovery and execution). A shorthand for {@see RunTiming::duration()}.
     */
    public function duration(): float
    {
        return $this->timing->duration();
    }

    /**
     * @return \Traversable<SuiteResult>
     */
    #[\Override]
    public function getIterator(): \Traversable
    {
        yield from $this->results;
    }
}
