<?php

declare(strict_types=1);

namespace Testo\Output\Json;

use Internal\Container\Container;
use Internal\Path;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Common\EventListenerCollector;
use Testo\Common\PluginConfigurator;
use Testo\Core\Report\ReportInfo;
use Testo\Event\Framework\SessionFinished;
use Testo\Event\Framework\SessionStarting;
use Testo\Event\Report\ReportFileGenerated;
use Testo\Event\Report\ReportFileGenerating;
use Testo\Output\Json\Internal\JsonReport;

/**
 * Renders the whole run as a single minimalistic JSON object on session end.
 *
 * Designed for machine consumers — CI scripts and, above all, LLM coding agents
 * that run the suite and read the result. Instead of the human-oriented progress
 * stream the {@see \Testo\Output\Terminal\TerminalPlugin} produces, this plugin
 * emits only what an agent needs to act on a failing run: the run status, the
 * per-status counts, and a flat list of failed tests with their throwable,
 * `previous` chain, stack trace, and captured output. See {@see JsonReport} for
 * the exact shape.
 *
 * # Two modes
 *
 * - **stdout** (default, `--json`) — the report is the only thing written to
 *   stdout, so it can be parsed without stripping anything. This is a stdout
 *   renderer: the `run` command activates exactly one of terminal/teamcity/json.
 * - **file** (`new JsonPlugin('build/report.json')`, `--log-json=<path>`) — the
 *   report is written to a file (parent directories created as needed) and the
 *   active stdout renderer is left untouched, so the human-readable terminal
 *   output and the machine-readable file coexist. Same idea as `--log-junit`.
 *
 * @api
 */
final class JsonPlugin implements PluginConfigurator
{
    /**
     * Destination file in file mode, or null in stdout mode.
     */
    private readonly ?Path $path;

    /** @var resource|null Stdout-mode stream, or null for {@see \STDOUT}. */
    private $stream;

    private readonly JsonReport $report;

    /**
     * @param string|resource|null $output Where the report goes. A non-empty string path writes the
     *        report to that file and leaves the active stdout renderer untouched (`--log-json` / file
     *        mode); a stream resource writes the report as the stdout output (`--json` / stdout mode);
     *        null defaults to {@see \STDOUT}. An empty string is stdout mode too, matching
     *        {@see \Testo\Output\JUnit\JUnitPlugin}.
     */
    public function __construct($output = null)
    {
        $this->path = \is_string($output) && $output !== '' ? Path::create($output) : null;
        $this->stream = \is_resource($output) ? $output : null;
        $this->report = new JsonReport();
    }

    #[\Override]
    public function configure(Container $container): void
    {
        $listeners = $container->get(EventListenerCollector::class);
        $listeners->addListener(SessionFinished::class, $this->onSessionFinished(...));

        $path = $this->path;
        if ($path === null) {
            return;
        }

        $info = new ReportInfo('json', 'JSON report', $path);
        $dispatcher = $container->get(EventDispatcherInterface::class);
        $listeners->addListener(
            SessionStarting::class,
            static fn(): mixed => $dispatcher->dispatch(new ReportFileGenerating($info)),
        );
        $listeners->addListener(
            SessionFinished::class,
            static fn(): mixed => $dispatcher->dispatch(new ReportFileGenerated($info)),
        );
    }

    private function onSessionFinished(SessionFinished $event): void
    {
        $json = $this->report->generate($event->result) . "\n";

        if ($this->path === null) {
            \fwrite($this->stream ?? \STDOUT, $json);
            return;
        }

        $dir = (string) $this->path->parent();
        \is_dir($dir) or \mkdir($dir, 0o755, true) or throw new \RuntimeException("Failed to create directory: {$dir}");
        \file_put_contents((string) $this->path, $json);
    }
}
