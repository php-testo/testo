<?php

declare(strict_types=1);

namespace Testo\Event\Report;

use Testo\Core\Report\ReportInfo;

/**
 * A report a run produces, announced to whoever renders the run's output.
 *
 * A reporter dispatches these instead of printing anything itself, so it never has to know which
 * renderer owns stdout. One event per report: a reporter that writes several formats, or one report per
 * suite, announces each of them.
 *
 * The payload is the reporter's card; the subclass fixes the kind of report and the moment —
 * {@see ReportFileGenerating} before a file is written, {@see ReportFileGenerated} after it. Subscribe
 * here to hear every announcement whatever its kind and moment, since the dispatcher matches an event's
 * parents, and to a concrete event when the moment is what you act on.
 *
 * @psalm-immutable
 * @api
 */
abstract readonly class ReportEvent
{
    public function __construct(
        public ReportInfo $info,
    ) {}
}
