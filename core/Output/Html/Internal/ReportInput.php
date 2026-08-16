<?php

declare(strict_types=1);

namespace Testo\Output\Html\Internal;

use Testo\Application\Config\Internal\Attribute\InflectableConfig;
use Testo\Application\Config\Internal\Attribute\InputOption;

/**
 * CLI input for the HTML reporter.
 *
 * Two independent destinations, because the page and the data it renders are separate artifacts: a CI job
 * may want only the document, an IDE only the page, a developer both.
 *
 * @internal
 * @psalm-internal Testo\Output\Html
 */
#[InflectableConfig]
final class ReportInput
{
    /**
     * `--log-html=<path>` — a directory to fill, or a `.html` file to write everything into.
     */
    #[InputOption('log-html')]
    public ?string $htmlPath = null;

    /**
     * `--log-report=<path>` — the report document on its own, for CI and external tooling.
     */
    #[InputOption('log-report')]
    public ?string $dataPath = null;
}
