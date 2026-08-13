<?php

declare(strict_types=1);

namespace Testo\Event\Report;

/**
 * Event triggered when a reporter commits to writing a report file — as early as its destination is
 * known, which for a reporter built from a whole run is the moment the session starts.
 *
 * The path is where the file **will** be: opening it on this event finds nothing, or a stale report from
 * an earlier run, and a run that dies before writing leaves the promise unfulfilled. The event exists to
 * state the artifact while the run's output is still open — the TeamCity renderer turns this one, and only
 * this one, into the `##teamcity[testoReport …]` service message an IDE parses, and a message arriving
 * after the last `testSuiteFinished` has no node in the run tree to attach to.
 *
 * {@see ReportFileGenerated} states the same file once it is written — the event to act on for anything
 * that reads the report rather than points at it.
 *
 * @psalm-immutable
 * @api
 */
final readonly class ReportFileGenerating extends ReportEvent {}
