<?php

declare(strict_types=1);

namespace Testo\Output\Html\Internal;

use Internal\Container\Container;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\RunConfiguration;
use Testo\Common\EventListenerCollector;
use Testo\Core\Context\RunResult;
use Testo\Core\Report\ReportInfo;
use Testo\Event\Framework\SessionFinished;
use Testo\Event\Framework\SessionStarting;
use Testo\Event\Report\ReportFileGenerated;
use Testo\Event\Report\ReportFileGenerating;

/**
 * Per-run collector that turns however many {@see \Testo\Output\Html\HtmlPlugin} instances are active
 * into a single report producer.
 *
 * Several instances can be live at once — most commonly the inert shadow default (which serves the
 * `--log-html` / `--log-report` flags) alongside a plugin declared in `testo.php`. Each contributes its
 * destinations here; the set is deduplicated, so the same path named by a configured plugin and by a
 * flag is written once. One {@see Recorder} feeds every destination and the document is built exactly
 * once — the report is a view over one run's data, not per-destination data — and the widest requested
 * `messageLimit` wins, since a single document cannot truncate two ways.
 *
 * The write runs from a {@see SessionFinished} listener at a low priority, after the run summary, so the
 * verdict prints before a large document is serialized and every artifact path a run states lands
 * together at the end.
 *
 * @internal
 * @psalm-internal Testo\Output\Html
 */
final class HtmlReportSink
{
    /**
     * Format id of the standalone document. Not plain `json`, which is what
     * {@see \Testo\Output\Json\JsonPlugin} writes: both files are JSON and neither is the other's schema,
     * so the id names the schema rather than the encoding.
     */
    public const DATA_FORMAT = 'testo-report';

    /** Lower runs later — see the class docblock. */
    private const LISTENER_PRIORITY = -100;

    private readonly Recorder $recorder;
    private readonly Writer $writer;

    /**
     * Captured here, not re-fetched when the events fire: the container hands out per-scope dispatcher
     * clones, and the one that reaches the app-scope renderers (which turn a `ReportFileGenerated` into a
     * printed line) is the one live at configuration time — the same reason the JUnit reporter captures it.
     */
    private readonly EventDispatcherInterface $dispatcher;

    private readonly RunConfiguration $config;

    /** @var list<non-empty-string> */
    private readonly array $suiteNames;

    /** @var array<non-empty-string, Destination> Keyed by {@see Destination::key()} to dedup. */
    private array $destinations = [];

    /** @var int<0, max> Widest truncation limit any contributor asked for. */
    private int $messageLimit = 0;

    public function __construct(Container $container)
    {
        $this->recorder = new Recorder();
        $this->writer = new Writer();
        $this->dispatcher = $container->get(EventDispatcherInterface::class);
        $this->config = $container->get(RunConfiguration::class);
        $this->suiteNames = self::suiteNames($container);

        $listeners = $container->get(EventListenerCollector::class);
        $this->recorder->configure($listeners);

        # The write is registered before the announcement so the late `Generated` follows it; both sit at
        # the same low priority, after the summary.
        $listeners->addListener(SessionFinished::class, $this->onSessionFinished(...), self::LISTENER_PRIORITY);
        $listeners->addListener(SessionStarting::class, $this->onSessionStarting(...), self::LISTENER_PRIORITY);
    }

    /**
     * Adds a plugin's destinations to the run's set and widens the truncation limit to fit.
     *
     * @param int<0, max> $messageLimit
     */
    public function contribute(int $messageLimit, Destination ...$destinations): void
    {
        $messageLimit > $this->messageLimit and $this->messageLimit = $messageLimit;

        foreach ($destinations as $destination) {
            $this->destinations[$destination->key()] = $destination;
        }
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
     * Promises every destination up front, while the run's output is still open — a `testoReport` service
     * message after the last `testSuiteFinished` has no node in an IDE's run tree to attach to.
     */
    private function onSessionStarting(): void
    {
        foreach ($this->destinations as $destination) {
            $this->dispatcher->dispatch(new ReportFileGenerating($this->info($destination)));
        }
    }

    private function onSessionFinished(SessionFinished $event): void
    {
        if ($this->destinations === []) {
            return;
        }

        $document = $this->build($event->result);

        foreach ($this->destinations as $destination) {
            $destination->kind === ReportKind::Html
                ? $this->writer->writeHtml($destination->path, $document)
                : $this->writer->writeData($destination->path, $document);

            $this->dispatcher->dispatch(new ReportFileGenerated($this->info($destination)));
        }
    }

    private function info(Destination $destination): ReportInfo
    {
        return $destination->kind === ReportKind::Html
            ? new ReportInfo('html', $destination->name, Writer::entryFile($destination->path))
            : new ReportInfo(self::DATA_FORMAT, $destination->name, $destination->path);
    }

    /**
     * @return array<non-empty-string, mixed>
     */
    private function build(RunResult $result): array
    {
        return (new DocumentBuilder(
            recorder: $this->recorder,
            config: $this->config,
            messageLimit: $this->messageLimit,
            suiteNames: $this->suiteNames,
        ))->build($result);
    }
}
