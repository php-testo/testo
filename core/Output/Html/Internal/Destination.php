<?php

declare(strict_types=1);

namespace Testo\Output\Html\Internal;

use Internal\Path;

/**
 * One place a run's report is written to: a path, what kind of output lands there, and the label an
 * announcement carries for it.
 *
 * A run collects a set of these — from configured plugins and from the CLI flags — and writes the one
 * document it built to each. {@see key()} dedups the set, so the same path named twice (a configured
 * plugin and a flag pointing at it) is written and announced once.
 *
 * @internal
 * @psalm-internal Testo\Output\Html
 */
final readonly class Destination
{
    /**
     * @param non-empty-string $name Announcement label — what an IDE shows on the button.
     */
    public function __construct(
        public Path $path,
        public ReportKind $kind,
        public string $name,
    ) {}

    /**
     * Identity for deduplication: the same kind written to the same path is the same destination,
     * whoever asked for it.
     *
     * @return non-empty-string
     */
    public function key(): string
    {
        return $this->kind->name . '|' . (string) $this->path;
    }
}
