<?php

declare(strict_types=1);

namespace Testo\Output\Json;

use Internal\Container\Container;
use Internal\Path;
use Testo\Common\EventListenerCollector;
use Testo\Common\PluginConfigurator;
use Testo\Event\Framework\SessionFinished;
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

    /** @var resource|null Stream used in stdout mode; resolved to {@see \STDOUT} on write. */
    private $stream;

    private readonly JsonReport $report;

    /**
     * @param string|null $outputPath When set to a non-empty path, the report is written to that
     *        file and the active stdout renderer is left untouched (`--log-json` / file mode). When
     *        null or an empty string, the report is written to {@see $stream} (`--json` / stdout
     *        mode) — empty is normalized to null, matching {@see \Testo\Output\JUnit\JUnitPlugin}.
     * @param resource|null $stream Stream for stdout mode; defaults to {@see \STDOUT}. Ignored
     *        when a file path is set.
     */
    public function __construct(?string $outputPath = null, $stream = null)
    {
        $this->path = $outputPath !== null && $outputPath !== '' ? Path::create($outputPath) : null;
        $this->stream = $stream;
        $this->report = new JsonReport();
    }

    #[\Override]
    public function configure(Container $container): void
    {
        $container->get(EventListenerCollector::class)
            ->addListener(SessionFinished::class, $this->onSessionFinished(...));
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
