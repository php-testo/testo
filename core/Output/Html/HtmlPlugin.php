<?php

declare(strict_types=1);

namespace Testo\Output\Html;

use Internal\Container\Container;
use Internal\Path;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\RunConfiguration;
use Testo\Common\EventListenerCollector;
use Testo\Common\PluginConfigurator;
use Testo\Core\Context\RunResult;
use Testo\Core\Report\ReportInfo;
use Testo\Event\Report\ReportFileGenerated;
use Testo\Event\Report\ReportFileGenerating;
use Testo\Event\Framework\SessionFinished;
use Testo\Event\Framework\SessionStarting;
use Testo\Output\Html\Internal\DocumentBuilder;
use Testo\Output\Html\Internal\Recorder;
use Testo\Output\Html\Internal\ReportInput;
use Testo\Output\Html\Internal\Writer;

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
 * `--log-report=<path>` flags have something to activate without any change to `testo.php`. Instances are
 * independent, and an instance configured in code ignores the flags entirely: as with the JUnit reporter,
 * adding `--log-html` to a run whose config already registers this plugin yields two reports rather than a
 * redirected one. Drop the default first to pin the report to a single path:
 * `ApplicationPlugins::without(HtmlPlugin::class)->with(new HtmlPlugin('build/report'))`.
 *
 * @api
 */
final class HtmlPlugin implements PluginConfigurator
{
    /** Where a report goes when nothing says otherwise: under the project's runtime directory. */
    public const DEFAULT_PATH = 'runtime/report';

    /**
     * Format id of the standalone document. Not plain `json`, which is what
     * {@see \Testo\Output\Json\JsonPlugin} writes: both files are JSON and neither is the other's schema,
     * so the id names the schema rather than the encoding.
     */
    private const DATA_FORMAT = 'testo-report';

    /**
     * The report is written from a `SessionFinished` listener, and so is the final summary. A lower
     * priority runs later, which puts the write — and the announcement that follows it — after the
     * summary: the reader gets the verdict without waiting for a large document to be serialized, and the
     * path lands with every other artifact path a run states at its end.
     */
    private const LISTENER_PRIORITY = -100;

    private readonly Recorder $recorder;
    private readonly Writer $writer;

    /** Destination of the page, or null when no page was asked for. */
    private ?Path $htmlPath;

    /** Destination of the standalone document, or null when it was not asked for. */
    private ?Path $dataPath;

    /**
     * Single-shot guard, matching {@see \Testo\Output\JUnit\JUnitPlugin}: one instance is shared by every
     * top-level run and by every nested {@see \Testo\Testing\Helper\TestRunner::runTest()} sub-run, and
     * attaching twice would have an inner session overwrite the outer session's report.
     */
    private bool $configured = false;

    private ?RunConfiguration $runConfiguration = null;

    /** @var list<non-empty-string> */
    private array $suiteNames = [];

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
        $this->recorder = new Recorder();
        $this->writer = new Writer();
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

        # Constructor paths win, the same way the JUnit reporter resolves its own: the flags are consulted
        # only for an instance given no destination at all, which is how the inert default gets activated.
        # A reporter configured in code owns both of its slots — a flag filling the empty one would add an
        # output behind the config's back, and the inert default is already writing that file.
        if ($this->htmlPath === null && $this->dataPath === null) {
            $input = $container->get(ReportInput::class);
            $this->htmlPath = self::path($input->htmlPath);
            $this->dataPath = self::path($input->dataPath);

            if ($this->htmlPath === null && $this->dataPath === null) {
                return;
            }
        }

        $this->runConfiguration = $container->get(RunConfiguration::class);
        $this->suiteNames = self::suiteNames($container);

        $listeners = $container->get(EventListenerCollector::class);
        $this->recorder->configure($listeners);
        $listeners->addListener(
            SessionFinished::class,
            $this->onSessionFinished(...),
            self::LISTENER_PRIORITY,
        );

        # Registered after the listener that writes the files, so the late announcement follows the write.
        $dispatcher = $container->get(EventDispatcherInterface::class);
        foreach ($this->announced() as $info) {
            $listeners->addListener(
                SessionStarting::class,
                static fn(): mixed => $dispatcher->dispatch(new ReportFileGenerating($info)),
                self::LISTENER_PRIORITY,
            );
            $listeners->addListener(
                SessionFinished::class,
                static fn(): mixed => $dispatcher->dispatch(new ReportFileGenerated($info)),
                self::LISTENER_PRIORITY,
            );
        }
    }

    private static function path(?string $path): ?Path
    {
        return $path === null || $path === '' ? null : Path::create($path);
    }

    /**
     * Every configured suite, including the ones that ran nothing: a suite filtered down to zero tests
     * leaves no trace in the results, and a report that omitted it would read as "no such suite".
     *
     * @return list<non-empty-string>
     */
    private static function suiteNames(Container $container): array
    {
        $names = [];
        foreach ($container->get(ApplicationConfig::class)->suites as $suite) {
            $names[] = $suite->name;
        }

        return $names;
    }

    /**
     * The cards for whatever this instance was asked to write: the standalone document, the page, or both.
     *
     * @return list<ReportInfo>
     */
    private function announced(): array
    {
        $cards = [];
        $this->dataPath === null or $cards[] = new ReportInfo(self::DATA_FORMAT, $this->name, $this->dataPath);
        $this->htmlPath === null or $cards[] = new ReportInfo(
            'html',
            $this->name,
            Writer::entryFile($this->htmlPath),
        );

        return $cards;
    }

    private function onSessionFinished(SessionFinished $event): void
    {
        $document = $this->build($event->result);

        $this->dataPath === null or $this->writer->writeData($this->dataPath, $document);
        $this->htmlPath === null or $this->writer->writeHtml($this->htmlPath, $document);
    }

    /**
     * @return array<non-empty-string, mixed>
     */
    private function build(RunResult $result): array
    {
        return (new DocumentBuilder(
            recorder: $this->recorder,
            config: $this->runConfiguration ?? new RunConfiguration(),
            messageLimit: $this->messageLimit,
            suiteNames: $this->suiteNames,
        ))->build($result);
    }
}
