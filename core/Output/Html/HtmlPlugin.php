<?php

declare(strict_types=1);

namespace Testo\Output\Html;

use Internal\Container\Container;
use Internal\Path;
use Testo\Common\PluginConfigurator;
use Testo\Output\Html\Internal\Destination;
use Testo\Output\Html\Internal\HtmlReportSink;
use Testo\Output\Html\Internal\ReportInput;
use Testo\Output\Html\Internal\ReportKind;

/**
 * Writes the run as a self-contained HTML report, and the document behind it as a standalone artifact.
 *
 * The report opens over `file://` with no server: relative paths, a classic script bundle, and the data
 * as a script assigning a global rather than a fetched `.json`. It reaches no network at all — no CDN,
 * no fonts, no analytics.
 *
 * # Layout
 *
 * The output path decides the layout, so there is no flag to keep in step with it:
 *
 * - a path ending in `.html` → everything inlined into that one file;
 * - anything else → a directory with `index.html` and an `assets/` folder beside it.
 *
 * A multi-file report is cheaper to diff and lets a browser cache the assets; a single file is one
 * artifact to attach to a CI build.
 *
 * # Activation
 *
 * ```
 * # The default location, runtime/report/index.html
 * ApplicationPlugins::with(new HtmlPlugin())
 *
 * # A single file, plus the document for external tooling
 * ApplicationPlugins::with(new HtmlPlugin('build/report.html', 'build/report.json'))
 * ```
 *
 * An inert copy — {@see inert()} — is part of the application defaults, so the `--log-html=<path>` and
 * `--log-report=<path>` flags have something to activate without any change to `testo.php`. An instance
 * configured in code owns its slots and ignores the flags; only an instance with no destination of its
 * own reads them, which is how the inert default gets activated. Every destination a run collects — from
 * configured plugins and from flags — feeds a single {@see HtmlReportSink}: the document is built once
 * and written to each, and a path named twice is written once. Drop the default to keep the report to a
 * single configured path: `ApplicationPlugins::without(HtmlPlugin::class)->with(new HtmlPlugin('build/report'))`.
 *
 * @api
 */
final class HtmlPlugin implements PluginConfigurator
{
    /** Where a report goes when nothing says otherwise: under the project's runtime directory. */
    public const DEFAULT_PATH = 'runtime/report';

    /** Destination of the page, or null when no page was asked for. */
    private readonly ?Path $htmlPath;

    /** Destination of the standalone document, or null when it was not asked for. */
    private readonly ?Path $dataPath;

    /**
     * Single-shot guard, matching {@see \Testo\Output\JUnit\JUnitPlugin}: one instance is shared by every
     * top-level run and by every nested {@see \Testo\Testing\Helper\TestRunner::runTest()} sub-run, and
     * contributing twice would have an inner session write over the outer session's report.
     */
    private bool $configured = false;

    /**
     * @param non-empty-string|null $outputPath Directory to fill, or a `.html` file to write everything
     *        into. Null means "no page unless `--log-html` asks for one".
     * @param non-empty-string|null $dataPath Where to write the report document on its own. Null means
     *        "not unless `--log-report` asks for it".
     * @param non-empty-string $name Human-readable label carried by the announcement; what an IDE shows
     *        on the button that opens the report.
     * @param int<0, max> $messageLimit Bytes of channel output kept per test. Output is the one part of a
     *        run with no natural size — a test that logs a loop can outweigh everything else in the
     *        document — so it is capped, and the report states where it cut.
     */
    public function __construct(
        ?string $outputPath = self::DEFAULT_PATH,
        ?string $dataPath = null,
        private readonly string $name = 'HTML report',
        private readonly int $messageLimit = 65536,
    ) {
        $this->htmlPath = self::path($outputPath);
        $this->dataPath = self::path($dataPath);
    }

    /**
     * A copy that writes nothing until a CLI flag gives it a path.
     *
     * This is what the application defaults hold: a project that never mentions the reporter still gets
     * `--log-html` and `--log-report`, and a project that never passes them gets no files.
     */
    public static function inert(): self
    {
        return new self(outputPath: null);
    }

    #[\Override]
    public function configure(Container $container): void
    {
        if ($this->configured) {
            return;
        }
        $this->configured = true;

        $destinations = $this->destinations($container);
        if ($destinations === []) {
            return;
        }

        # First contributor creates the run's sink and wires it; the rest add their destinations to it.
        $sink = $container->has(HtmlReportSink::class)
            ? $container->get(HtmlReportSink::class)
            : self::createSink($container);

        $sink->contribute($this->messageLimit, ...$destinations);
    }

    private static function createSink(Container $container): HtmlReportSink
    {
        $sink = new HtmlReportSink($container);
        $container->set($sink);

        return $sink;
    }

    private static function path(?string $path): ?Path
    {
        return $path === null || $path === '' ? null : Path::create($path);
    }

    /**
     * This instance's destinations: its own if it was given any, otherwise whatever the CLI flags name.
     *
     * Constructor paths win — an instance configured in code never reads the flags, so a flag filling an
     * empty slot cannot add an output behind the config's back. Only an instance with no destination of
     * its own (the inert default) falls through to the flags.
     *
     * @return list<Destination>
     */
    private function destinations(Container $container): array
    {
        $destinations = [];
        $this->htmlPath === null or $destinations[] = new Destination($this->htmlPath, ReportKind::Html, $this->name);
        $this->dataPath === null or $destinations[] = new Destination($this->dataPath, ReportKind::Data, $this->name);

        if ($destinations !== []) {
            return $destinations;
        }

        $input = $container->get(ReportInput::class);
        $html = self::path($input->htmlPath);
        $data = self::path($input->dataPath);
        $html === null or $destinations[] = new Destination($html, ReportKind::Html, $this->name);
        $data === null or $destinations[] = new Destination($data, ReportKind::Data, $this->name);

        return $destinations;
    }
}
