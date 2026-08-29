<?php

declare(strict_types=1);

namespace Testo\Output\Terminal\Renderer;

use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Common\Environment;
use Testo\Common\Messenger;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\RunResult;
use Testo\Core\Context\SuiteInfo;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Log\Message;
use Testo\Core\Log\MessageLog;
use Testo\Core\Value\Status;
use Testo\Core\Value\Verbosity;
use Testo\Data\MultipleResult;
use Testo\Core\Report\ReportInfo;
use Testo\Output\Rendering\ChannelRenderer;
use Testo\Output\Rendering\SharedStream;

/**
 * Terminal logger for test reporting with configurable output format.
 *
 * @internal
 */
final class TerminalLogger
{
    /** @var list<array{result: TestResult, duration: int<0, max>|null, suiteName: string|null, datasetName: string|null}> */
    private array $failures = [];

    /**
     * Indentation level of each test in flight (nested for DataProvider datasets), keyed by
     * {@see \Testo\Core\Context\Identity\TestIdentity::$pipelineId}. A single counter is clobbered the moment two
     * tests interleave: the one entering a batch would indent the other's lines.
     *
     * @var array<int, int<0, max>>
     */
    private array $currentIndentLevel = [];

    /**
     * Override name of each test in flight (e.g. the running data set's name), keyed by test id.
     *
     * @var array<int, non-empty-string>
     */
    private array $currentTestName = [];

    /**
     * Current suite name for failure context.
     */
    private ?string $currentSuiteName = null;

    /**
     * The most recently handled test result. When the whole run turns out to be a single test, this
     * is that test — already the leaf result (a data set, not the aggregate batch) with its own
     * captured messages, so no result-tree walking is needed to surface its output.
     */
    private ?TestResult $lastResult = null;

    private readonly SharedStream $out;

    /** @var resource */
    private $errorOutput;

    /**
     * Channel grouping of each open block, keyed by test id. Its life is the block's life — created on
     * the test's first write, dropped by {@see closeTest()} — so it never carries state across into
     * another test's block, and a block always opens with a fresh channel header.
     *
     * @var array<int, ChannelRenderer>
     */
    private array $channels = [];

    /**
     * Channel grouping of output that belongs to no test, which is written through rather than blocked.
     */
    private ?ChannelRenderer $unownedChannels = null;

    /**
     * @param resource|null $output Stream for the human-facing report; defaults to {@see \STDOUT}.
     *        Output goes straight to the stream, bypassing PHP output buffering, so it is not captured
     *        by the messenger's output interceptor.
     * @param resource|null $errorOutput Stream for the internal {@see Messenger::CHANNEL_STDERR}
     *        channel (framework faults); defaults to {@see \STDERR} so those messages never corrupt
     *        the structured report on {@see \STDOUT} (which `--json` / `--teamcity` and CI parse).
     */
    public function __construct(
        private readonly OutputFormat $format = OutputFormat::Compact,
        private readonly Verbosity $verbosity = Verbosity::Normal,
        $output = null,
        $errorOutput = null,
    ) {
        $this->out = new SharedStream($output ?? \STDOUT);
        $this->errorOutput = $errorOutput ?? \STDERR;
    }

    /**
     * Publishes test suite started message.
     */
    public function suiteStartedFromInfo(SuiteInfo $info): void
    {
        $this->currentSuiteName = $info->name;
        $this->write(null, Formatter::suiteHeader($info->name, $this->format));
    }

    /**
     * Handles test suite result.
     */
    public function handleSuiteResult(SuiteInfo $info, SuiteResult $result): void
    {
        $this->write(null, Formatter::suiteSummary($result));
    }

    /**
     * Publishes test case started message.
     */
    public function caseStartedFromInfo(CaseInfo $info): void
    {
        $this->write(null, Formatter::caseHeader($info->name, $this->format));
    }

    /**
     * Handles test case result.
     */
    public function handleCaseResult(CaseInfo $info, CaseResult $result): void
    {
        # Every test of the case has finished by now, so nothing should still be held — but a test the
        # runner never closed would otherwise land under the *next* case's header. Its per-test state
        # goes with it, so a long session does not accumulate entries no close will ever drop.
        $this->out->flush();
        $this->channels = [];
        $this->currentTestName = [];
        $this->currentIndentLevel = [];

        $this->write(null, Formatter::caseFooter($this->format));
        $this->write(null, Formatter::caseSummary($result, $this->format));
    }

    /**
     * Publishes test batch started message (for DataProvider tests).
     */
    public function batchStartedFromInfo(TestInfo $info): void
    {
        $this->currentIndentLevel[$info->identity->pipelineId] = 1;

        if ($this->format === OutputFormat::Dots) {
            return;
        }

        // Print the batch test name (the main test with DataProvider)
        $indent = $this->format === OutputFormat::Verbose ? '     ' : '   ';
        $symbol = Style::dim(Symbol::DataProvider->value);
        $id = $info->identity->pipelineId;
        $this->write($id, "{$indent}{$symbol} {$info->name}\n");

        // The description belongs to the test, not to each dataset — print it once here, at the root
        // of the dataset tree, so it is not repeated under every dataset (see handle*Test).
        $this->write($id, Formatter::description(
            (string) $info->testDefinition->getDescription(),
            0,
            $this->format,
        ));
    }

    /**
     * Publishes test batch finished message (for DataProvider tests).
     */
    public function batchFinishedFromInfo(TestInfo $info): void
    {
        unset($this->currentIndentLevel[$info->identity->pipelineId]);
        // No visual output for batch finish in terminal mode
    }

    /**
     * Publishes test started message.
     *
     * @param non-empty-string|null $overrideName Optional override for the test name (e.g., dataset name)
     */
    public function testStartedFromInfo(TestInfo $info, ?string $overrideName = null): void
    {
        $id = $info->identity->pipelineId;
        if ($overrideName === null) {
            # A regular test carries no override — it prints under its own name.
            unset($this->currentTestName[$id]);
        } else {
            $this->currentTestName[$id] = $overrideName;
        }
        // No output on test start for compact/dots mode
    }

    /**
     * Resets the test's channel grouping so its next message prints a fresh channel header. Used at
     * data set boundaries: data sets share the batch's block, so nothing else would separate them.
     */
    public function resetChannels(TestInfo $info): void
    {
        ($this->channels[$info->identity->pipelineId] ?? null)?->reset();
    }

    /**
     * The test is done writing: release its block onto the stream and forget its channel grouping.
     *
     * Must come after the last write for the test — its result line included — or that line would be
     * carried over into whatever block opens next.
     */
    public function closeTest(TestInfo $info): void
    {
        $id = $info->identity->pipelineId;
        unset($this->channels[$id]);
        $this->out->close($id);
    }

    /**
     * Streams a captured channel {@see Message} to the terminal in real time.
     *
     * A colored channel header (name + time of the group's first message) is printed when the group
     * changes; same-group content is appended verbatim. Live streaming happens only at
     * {@see Verbosity::Verbose} and above; at lower verbosity the output of a *failed* test is shown
     * after the fact (see {@see printFailures()}). Suppressed in Dots mode, whose single-character
     * per-test layout multi-line output would break.
     *
     * @param int|null $owner Run of the test the message belongs to
     *        ({@see \Testo\Core\Context\Identity\TestIdentity::$pipelineId}), which decides both the
     *        block it lands in and the channel grouping it continues. `null` for output owned by no
     *        test.
     */
    public function logMessage(Message $message, ?int $owner = null): void
    {
        if ($message->content === '') {
            return;
        }

        # Internal errors on the dedicated stderr channel always go to the real STDERR stream as-is,
        # regardless of verbosity or format, so a framework fault stays visible without corrupting the
        # structured report on STDOUT (parsed by `--json` / `--teamcity` and CI).
        if ($message->channel === Messenger::CHANNEL_STDERR) {
            \fwrite($this->errorOutput, \rtrim($message->content, "\n") . "\n");
            return;
        }

        # Every other channel streams only at Verbose+ and is suppressed in the Dots layout (whose
        # single-character-per-test format multi-line output would break).
        if ($this->format === OutputFormat::Dots || !$this->verbosity->atLeast(Verbosity::Verbose)) {
            return;
        }

        $this->write($owner, $this->channelsFor($owner)->render($message));
    }

    /**
     * Handles test result and updates statistics.
     *
     * @param int<0, max>|null $duration Duration in milliseconds
     */
    public function handleTestResult(TestResult $result, ?int $duration): void
    {
        $this->lastResult = $result;

        match ($result->status) {
            Status::Passed, Status::Flaky => $this->handlePassedTest($result, $duration),
            Status::Failed, Status::Error, Status::Aborted => $this->handleFailedTest($result, $duration),
            Status::Skipped, Status::Cancelled => $this->handleSkippedTest($result, $duration),
            Status::Risky => $this->handleRiskyTest($result, $duration),
        };
    }

    /**
     * Prints final summary with all failures and statistics.
     */
    public function printSummary(RunResult $result): void
    {
        # Last chance for anything still held — a test the run never closed (aborted, timed out) must
        # not be swallowed by the end of the report.
        $this->out->flush();

        $this->printFailures();
        $this->printSingleTestOutput($result);
        $this->printStatistics($result);
    }

    /**
     * States a written report as one line, with the path in the form the reporter holds it.
     */
    public function printReport(ReportInfo $report): void
    {
        $this->write(null, \sprintf(
            ' %s %s %s',
            Style::info($report->name . ':'),
            $report->path,
            Style::dim('(' . $report->format . ')'),
        ) . "\n");
    }

    /**
     * Ensures run header is printed once.
     */
    public function ensureHeader(): void
    {
        $this->write(null, Formatter::runHeader());
    }

    public function printEnvironment(): void
    {
        $this->write(null, \sprintf(' %s %s (%s)', Style::info('OS:'), Environment::getOs(), Environment::getCpu()) . "\n");
        $this->write(null, \sprintf(' %s %s (%s, memory: %s)', Style::info('PHP:'), Environment::getPhpVersion(), \PHP_SAPI, \ini_get('memory_limit') ?: 'unlimited') . "\n");

        $modes = Environment::getXDebugMode();
        $xdebug = match (true) {
            !Environment::hasXDebug() => 'off',
            $modes !== [] => Environment::getXDebugVersion() . Style::dim(' (' . \implode(', ', $modes) . ')'),
            default => Environment::getXDebugVersion() . Style::dim(' (off)'),
        };
        $this->write(null, \sprintf('   %s %s', Style::info('XDebug:'), $xdebug) . "\n");

        $opcache = match (true) {
            !Environment::isOpCacheEnabled() => 'off',
            Environment::isJitEnabled() => 'enabled with JIT',
            default => 'enabled',
        };
        $this->write(null, \sprintf('   %s %s', Style::info('OPcache:'), $opcache) . "\n\n");
    }

    /**
     * Renders a test's captured messages as grouped channel output (with `[channel]` headers), or
     * an empty string when there are none. Used to show a failed test's output after the fact when
     * it was not streamed live.
     */
    private static function renderMessages(MessageLog $messages): string
    {
        if ($messages->isEmpty()) {
            return '';
        }

        $renderer = new ChannelRenderer();
        $output = '';
        foreach ($messages as $message) {
            $message->content === '' or $output .= $renderer->render($message);
        }

        return $output === '' ? '' : Style::dim('Output:') . "\n" . $output;
    }

    /**
     * Builds a fully qualified test name with suite, case, method, and dataset.
     *
     * Format: Suite / CaseName :: methodName > DatasetName
     *
     * @return non-empty-string
     */
    private static function buildFullTestName(
        TestInfo $info,
        ?string $suiteName,
        ?string $datasetName,
    ): string {
        $parts = [];

        $suiteName !== null and $parts[] = $suiteName;
        $parts[] = $info->caseInfo->name;

        $name = \implode(' / ', $parts) . ' :: ' . $info->name;

        $datasetName !== null and $name .= ' > ' . $datasetName;

        return $name;
    }

    /**
     * When the whole run consisted of a single test, surface its captured channel output after the
     * fact. Running exactly one test is almost always an interactive "run this and show me everything"
     * invocation, so its channels are worth printing even for a passing test — which normally stays
     * quiet at {@see Verbosity::Normal}.
     *
     * Skipped when the output was already streamed live ({@see Verbosity::Verbose} and above),
     * when the user asked for less noise ({@see Verbosity::Quiet}), in the single-character Dots
     * layout, and for a failed/aborted test whose output {@see printFailures()} already prints.
     */
    private function printSingleTestOutput(RunResult $result): void
    {
        $test = $this->lastResult;
        if (
            $test === null
            || $result->summary->total() !== 1
            || $this->format === OutputFormat::Dots
            || $this->verbosity !== Verbosity::Normal
            || $test->status->isFailure()
            || $test->status === Status::Aborted
        ) {
            return;
        }

        $output = self::renderMessages($test->messages);
        $output === '' or $this->write(null, "\n" . $output);
    }

    /**
     * @param int|null $owner Test whose block the text belongs to; `null` for report structure that
     *        belongs to no test (suite header, case footer, final summary).
     */
    private function write(?int $owner, string $text): void
    {
        $this->out->write($owner, $text);
    }

    /**
     * Channel grouping for the given owner's block, opened on demand.
     */
    private function channelsFor(?int $owner): ChannelRenderer
    {
        return $owner === null
            ? $this->unownedChannels ??= new ChannelRenderer()
            : $this->channels[$owner] ??= new ChannelRenderer();
    }

    /**
     * Handles passed test status.
     *
     * @param int<0, max>|null $duration
     */
    private function handlePassedTest(TestResult $result, ?int $duration): void
    {
        $item = new FormattedItem(
            name: $this->displayName($result),
            status: $result->status,
            duration: $duration,
            indentLevel: $this->indentLevel($result),
            description: $this->resultDescription($result),
        );

        $this->write($result->info->identity->pipelineId, Formatter::formatRun($item, $this->format));
        $this->printMultipleRuns($result);
        unset($this->currentTestName[$result->info->identity->pipelineId]);
    }

    /**
     * Handles failed test status.
     *
     * @param int<0, max>|null $duration
     */
    private function handleFailedTest(TestResult $result, ?int $duration): void
    {
        $this->failures[] = [
            'result' => $result,
            'duration' => $duration,
            'suiteName' => $this->currentSuiteName,
            'datasetName' => $this->currentTestName[$result->info->identity->pipelineId] ?? null,
        ];

        $item = new FormattedItem(
            name: $this->displayName($result),
            status: $result->status,
            duration: $duration,
            indentLevel: $this->indentLevel($result),
            description: $this->resultDescription($result),
        );

        $this->write($result->info->identity->pipelineId, Formatter::formatRun($item, $this->format));
        $this->printMultipleRuns($result);
        unset($this->currentTestName[$result->info->identity->pipelineId]);
    }

    /**
     * Name to print for a finished test: the override recorded for it (a data set's name), or its own.
     *
     * @return non-empty-string
     */
    private function displayName(TestResult $result): string
    {
        return $this->currentTestName[$result->info->identity->pipelineId] ?? $result->info->name;
    }

    /**
     * Indentation recorded for the test — 1 inside its DataProvider batch, 0 at the top level.
     *
     * @return int<0, max>
     */
    private function indentLevel(TestResult $result): int
    {
        return $this->currentIndentLevel[$result->info->identity->pipelineId] ?? 0;
    }

    /**
     * The description to show under a test run. Inside a DataProvider batch it is already printed once
     * at the batch node ({@see batchStartedFromInfo}), so it is suppressed here to avoid repeating the
     * same description under every dataset.
     */
    private function resultDescription(TestResult $result): string
    {
        return $this->indentLevel($result) > 0 ? '' : (string) $result->getAttribute('description');
    }

    /**
     * Prints multiple test runs if available.
     */
    private function printMultipleRuns(TestResult $result): void
    {
        if ($this->format === OutputFormat::Dots) {
            return;
        }

        $multipleResult = $result->getAttribute(MultipleResult::class);

        if ($multipleResult === null) {
            return;
        }

        $runNumber = 1;
        foreach ($multipleResult->results as $runKey => $runResult) {
            $item = new FormattedItem(
                name: "Run #{$runNumber}",
                status: $runResult->status,
                indentLevel: 1,
                description: (string) $runKey,
            );

            $this->write($result->info->identity->pipelineId, Formatter::formatRun($item, $this->format));
            $runNumber++;
        }
    }

    /**
     * Handles skipped test status.
     *
     * @param int<0, max>|null $duration
     */
    private function handleSkippedTest(TestResult $result, ?int $duration): void
    {
        $item = new FormattedItem(
            name: $this->displayName($result),
            status: $result->status,
            duration: $duration,
            indentLevel: $this->indentLevel($result),
        );

        $this->write($result->info->identity->pipelineId, Formatter::formatRun($item, $this->format));
        unset($this->currentTestName[$result->info->identity->pipelineId]);
    }

    /**
     * Handles risky test status.
     *
     * @param int<0, max>|null $duration
     */
    private function handleRiskyTest(TestResult $result, ?int $duration): void
    {
        $item = new FormattedItem(
            name: $this->displayName($result),
            status: $result->status,
            duration: $duration,
            indentLevel: $this->indentLevel($result),
        );

        $this->write($result->info->identity->pipelineId, Formatter::formatRun($item, $this->format));
        unset($this->currentTestName[$result->info->identity->pipelineId]);
    }

    /**
     * Prints all failures with details.
     */
    private function printFailures(): void
    {
        if ($this->failures === []) {
            return;
        }

        $this->write(null, Formatter::failuresHeader());

        $index = 1;
        foreach ($this->failures as $failure) {
            $result = $failure['result'];
            $duration = $failure['duration'];
            $throwable = $result->failure;

            $message = $throwable?->getMessage() ?? 'Test failed';
            $details = $throwable !== null
                ? Helper::formatException(
                    $throwable,
                    function: $result->info->testDefinition->reflection,
                    maxPreviousDepth: 1,
                )
                : '';

            if ($throwable instanceof ComparisonFailure) {
                $diffBlock = Formatter::comparisonBlock($throwable);
                $details = $details === '' ? $diffBlock : $diffBlock . "\n\n" . $details;
            }

            // At lower verbosity nothing was streamed live — surface the test's captured output here.
            if (!$this->verbosity->atLeast(Verbosity::Verbose)) {
                $output = self::renderMessages($result->messages);
                $output === '' or $details = $details === '' ? $output : $details . "\n\n" . $output;
            }

            $testName = self::buildFullTestName(
                $result->info,
                $failure['suiteName'],
                $failure['datasetName'],
            );

            $reflection = $result->info->testDefinition->reflection;
            $file = $reflection->getFileName();
            $line = $reflection->getStartLine();
            $location = $file !== false && $line !== false
                ? "{$file}:{$line}"
                : null;

            $this->write(null, Formatter::failureDetail(
                $index,
                $testName,
                $message,
                $details,
                $duration,
                $location,
            ));

            $index++;
        }
    }

    /**
     * Prints final statistics.
     */
    private function printStatistics(RunResult $result): void
    {
        $summary = $result->summary;

        $this->write(null, Formatter::summary($summary, $result->timing));

        if ($summary->total() === 0) {
            $this->write(null, Formatter::emptyBanner());
            return;
        }

        $failures = $summary->failed() + $summary->count(Status::Aborted);
        $this->write(null, Formatter::finalBanner($failures === 0));
    }
}
