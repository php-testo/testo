<?php

declare(strict_types=1);

namespace Testo\Core\Report;

/**
 * What a reporter knows about the report it writes: how to identify it, what to call it, and where it
 * lands.
 *
 * A reporter holds this from the moment its destination is settled — before the run, in the general case —
 * and the announcement events carry it as their payload.
 *
 * @psalm-immutable
 * @api
 */
final readonly class ReportInfo
{
    /**
     * @param non-empty-string $format Machine-readable format id, e.g. `html` or `junit`. Names the dialect
     *        a consumer would parse, not the file extension.
     * @param non-empty-string $name Human-readable label for the report.
     * @param \Stringable $path Where the report can be reached: an {@see \Internal\Path} for a file — the
     *        entry file, `index.html` for a multi-file layout, never the directory — or a URL for a report
     *        published to a service. Whatever form the reporter holds it in; a consumer that needs another
     *        derives it from the string.
     */
    public function __construct(
        public string $format,
        public string $name,
        public \Stringable $path,
    ) {}
}
