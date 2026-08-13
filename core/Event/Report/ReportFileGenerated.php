<?php

declare(strict_types=1);

namespace Testo\Event\Report;

/**
 * Event triggered after a reporter has written a report file and closed it.
 *
 * The file exists and is complete, which makes this the event to act on for anything that reads the
 * report — a checksum, an upload, a follow-up artifact. A renderer states it as a plain line; the TeamCity
 * service message goes out earlier, on {@see ReportFileGenerating}.
 *
 * @psalm-immutable
 * @api
 */
final readonly class ReportFileGenerated extends ReportEvent {}
