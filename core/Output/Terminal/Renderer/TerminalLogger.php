<?php

declare(strict_types=1);

namespace Testo\Output\Terminal\Renderer;

use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Common\Environment;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\SuiteInfo;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Log\Message;
use Testo\Core\Log\MessageLog;
use Testo\Core\Value\Status;
use Testo\Core\Value\Verbosity;
use Testo\Data\MultipleResult;
use Testo\Output\Rendering\ChannelRenderer;

/**
 * Terminal logger for test reporting with configurable output format.
 *
 * @internal
 */
final class TerminalLogger
{
    /** @var int<0, max> */
    private int $totalTests = 0;

    /** @var array<string, int<0, max>> Per-Status counters, keyed by Status::name */
    private array $statusCounts = [];

    /** @var list<array{result: TestResult, duration: int<0, max>|null, suiteName: string|null, datasetName: string|null}> */
    private array $failures = [];

    /**
     * Current indentation level for nested tests (e.g., DataProvider datasets).
     *
     * @var int<0, max>
     */
    private int $currentIndentLevel = 0;

    /**
     * Override name for the current test (e.g., dataset name).
     */
    private ?string $currentTestName = null;

    /**
     * Current suite name for failure context.
     */
    private ?string $currentSuiteName = null;

    /** @var resource */
    private $output;

    private readonly ChannelRenderer $channels;

    /**
     * @param resource|null $output Stream to write to; defaults to {@see \STDOUT}. Output goes
     *        straight to the stream, bypassing PHP output buffering, so it is not captured by the
     *        messenger's output interceptor.
     */
    public function __construct(
        private readonly OutputFormat $format = OutputFormat::Compact,
        private readonly Verbosity $verbosity = Verbosity::Normal,
        $output = null,
    ) {
        $this->output = $output ?? \STDOUT;
        $this->channels = new ChannelRenderer();
    }

    /**
     * Publishes test suite started message.
     */
    public function suiteStartedFromInfo(SuiteInfo $info): void
    {
        $this->currentSuiteName = $info->name;
        $this->write(Formatter::suiteHeader($info->name, $this->format));
    }

    /**
     * Handles test suite result.
     */
    public function handleSuiteResult(SuiteInfo $info, SuiteResult $result): void
    {
        $this->write(Formatter::suiteSummary($result, $this->format));
    }

    /**
     * Publishes test case started message.
     */
    public function caseStartedFromInfo(CaseInfo $info): void
    {
        $this->write(Formatter::caseHeader($info->name, $this->format));
    }

    /**
     * Handles test case result.
     */
    public function handleCaseResult(CaseInfo $info, CaseResult $result): void
    {
        $this->write(Formatter::caseFooter($this->format));
        $this->write(Formatter::caseSummary($result, $this->format));
    }

    /**
     * Publishes test batch started message (for DataProvider tests).
     */
    public function batchStartedFromInfo(TestInfo $info): void
    {
        $this->currentIndentLevel = 1;

        if ($this->format === OutputFormat::Dots) {
            return;
        }

        // Print the batch test name (the main test with DataProvider)
        $indent = $this->format === OutputFormat::Verbose ? '     ' : '   ';
        $symbol = Style::dim(Symbol::DataProvider->value);
        $this->write("{$indent}{$symbol} {$info->name}\n");
    }

    /**
     * Publishes test batch finished message (for DataProvider tests).
     */
    public function batchFinishedFromInfo(TestInfo $info): void
    {
        $this->currentIndentLevel = 0;
        // No visual output for batch finish in terminal mode
    }

    /**
     * Publishes test started message.
     *
     * @param non-empty-string|null $overrideName Optional override for the test name (e.g., dataset name)
     */
    public function testStartedFromInfo(TestInfo $info, ?string $overrideName = null): void
    {
        $this->currentTestName = $overrideName;
        // No output on test start for compact/dots mode
    }

    /**
     * Resets channel grouping so the next test's first message prints a fresh channel header.
     */
    public function resetChannels(): void
    {
        $this->channels->reset();
    }

    /**
     * Streams a captured channel {@see Message} to the terminal in real time.
     *
     * A colored channel header (name + time of the channel's first message) is printed when the
     * channel changes; same-channel content is appended verbatim. Live streaming happens only at
     * {@see Verbosity::Verbose} and above; at lower verbosity the output of a *failed* test is shown
     * after the fact (see {@see printFailures()}). Suppressed in Dots mode, whose single-character
     * per-test layout multi-line output would break.
     */
    public function logMessage(Message $message): void
    {
        if (
            $message->content === ''
            || $this->format === OutputFormat::Dots
            || !$this->verbosity->atLeast(Verbosity::Verbose)
        ) {
            return;
        }

        $this->write($this->channels->render($message));
    }

    /**
     * Handles test result and updates statistics.
     *
     * @param int<0, max>|null $duration Duration in milliseconds
     */
    public function handleTestResult(TestResult $result, ?int $duration): void
    {
        $this->totalTests++;
        $this->statusCounts[$result->status->name] = ($this->statusCounts[$result->status->name] ?? 0) + 1;

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
    public function printSummary(float $duration): void
    {
        $this->printFailures();
        $this->printStatistics($duration);
    }

    /**
     * Ensures run header is printed once.
     */
    public function ensureHeader(): void
    {
        $this->write(Formatter::runHeader());
    }

    public function printEnvironment(): void
    {
        $this->write(\sprintf(' %s %s (%s)', Style::info('OS:'), Environment::getOs(), Environment::getCpu()) . "\n");
        $this->write(\sprintf(' %s %s (%s, memory: %s)', Style::info('PHP:'), Environment::getPhpVersion(), \PHP_SAPI, \ini_get('memory_limit') ?: 'unlimited') . "\n");

        $modes = Environment::getXDebugMode();
        $xdebug = match (true) {
            !Environment::hasXDebug() => 'off',
            $modes !== [] => Environment::getXDebugVersion() . Style::dim(' (' . \implode(', ', $modes) . ')'),
            default => Environment::getXDebugVersion() . Style::dim(' (off)'),
        };
        $this->write(\sprintf('   %s %s', Style::info('XDebug:'), $xdebug) . "\n");

        $opcache = match (true) {
            !Environment::isOpCacheEnabled() => 'off',
            Environment::isJitEnabled() => 'enabled with JIT',
            default => 'enabled',
        };
        $this->write(\sprintf('   %s %s', Style::info('OPcache:'), $opcache) . "\n\n");
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

    private function write(string $text): void
    {
        \fwrite($this->output, $text);
    }

    /**
     * Handles passed test status.
     *
     * @param int<0, max>|null $duration
     */
    private function handlePassedTest(TestResult $result, ?int $duration): void
    {
        $item = new FormattedItem(
            name: $this->currentTestName ?? $result->info->name,
            status: $result->status,
            duration: $duration,
            indentLevel: $this->currentIndentLevel,
            description: (string) $result->getAttribute('description'),
        );

        $this->write(Formatter::formatRun($item, $this->format));
        $this->printMultipleRuns($result);
        $this->currentTestName = null;
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
            'datasetName' => $this->currentTestName,
        ];

        $item = new FormattedItem(
            name: $this->currentTestName ?? $result->info->name,
            status: $result->status,
            duration: $duration,
            indentLevel: $this->currentIndentLevel,
            description: (string) $result->getAttribute('description'),
        );

        $this->write(Formatter::formatRun($item, $this->format));
        $this->printMultipleRuns($result);
        $this->currentTestName = null;
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
                duration: null,
                indentLevel: 1,
                description: (string) $runKey,
            );

            $this->write(Formatter::formatRun($item, $this->format));
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
            name: $this->currentTestName ?? $result->info->name,
            status: $result->status,
            duration: $duration,
            indentLevel: $this->currentIndentLevel,
        );

        $this->write(Formatter::formatRun($item, $this->format));
        $this->currentTestName = null;
    }

    /**
     * Handles risky test status.
     *
     * @param int<0, max>|null $duration
     */
    private function handleRiskyTest(TestResult $result, ?int $duration): void
    {
        $item = new FormattedItem(
            name: $this->currentTestName ?? $result->info->name,
            status: $result->status,
            duration: $duration,
            indentLevel: $this->currentIndentLevel,
        );

        $this->write(Formatter::formatRun($item, $this->format));
        $this->currentTestName = null;
    }

    /**
     * Prints all failures with details.
     */
    private function printFailures(): void
    {
        if ($this->failures === []) {
            return;
        }

        $this->write(Formatter::failuresHeader());

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

            $this->write(Formatter::failureDetail(
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
    private function printStatistics(float $duration): void
    {
        $failures = ($this->statusCounts[Status::Failed->name] ?? 0)
            + ($this->statusCounts[Status::Error->name] ?? 0)
            + ($this->statusCounts[Status::Aborted->name] ?? 0);
        $success = $failures === 0;

        $this->write(Formatter::summary(
            $this->totalTests,
            $this->statusCounts,
            $duration,
        ));

        $this->write(Formatter::finalBanner($success));
    }
}
