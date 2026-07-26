<?php

declare(strict_types=1);

namespace Testo\Output\Terminal;

use Internal\Container\Container;
use Testo\Common\EventListenerCollector;
use Testo\Common\PluginConfigurator;
use Testo\Event\Framework\SessionFinished;
use Testo\Event\Framework\SessionStarting;
use Testo\Event\Message\MessageReceived;
use Testo\Event\Test\TestBatchFinished;
use Testo\Event\Test\TestBatchStarting;
use Testo\Event\Test\TestDataSetFinished;
use Testo\Event\Test\TestDataSetStarting;
use Testo\Event\Test\TestPipelineFinished;
use Testo\Event\Test\TestPipelineStarting;
use Testo\Event\TestCase\TestCaseFinished;
use Testo\Event\TestCase\TestCaseStarting;
use Testo\Event\TestSuite\TestSuiteFinished;
use Testo\Event\TestSuite\TestSuiteStarting;
use Testo\Output\Terminal\Renderer\ColorMode;
use Testo\Output\Terminal\Renderer\Style;
use Testo\Output\Terminal\Renderer\TerminalLogger;

/**
 * Terminal interceptor for rendering test results with configurable output.
 *
 * Implements StdoutRenderer to ensure only one stdout renderer is active.
 * Supports multiple output formats (Compact, Verbose, Dots) and color modes.
 */
final class TerminalPlugin implements PluginConfigurator
{
    /**
     * Ids of the tests currently inside a DataProvider batch.
     *
     * Keyed by {@see \Testo\Core\Context\TestIdentity::$id} so concurrently running tests are tracked
     * independently, and so the key never depends on an object address the way `spl_object_hash()` did.
     *
     * @var array<int, bool>
     */
    private array $isBatch = [];

    /**
     * Display name of every test currently in flight (the data set's name while one is running), keyed
     * by test id. Streamed output is attributed through this map; a test with no entry is not running.
     *
     * @var array<int, non-empty-string>
     */
    private array $running = [];

    public function __construct(
        private readonly TerminalLogger $logger,
        ColorMode $colorMode = ColorMode::Always,
    ) {
        // Configure color support based on mode
        Style::setColorsEnabled($colorMode->shouldUseColors());
    }

    #[\Override]
    public function configure(Container $container): void
    {
        $listeners = $container->get(EventListenerCollector::class);

        // Framework events
        $listeners->addListener(SessionStarting::class, $this->onSessionStarting(...));
        $listeners->addListener(SessionFinished::class, $this->onSessionFinished(...));

        // Messenger output — streamed to the terminal in real time for the running test.
        $listeners->addListener(MessageReceived::class, $this->onMessageReceived(...));

        // Test Pipeline events (lifecycle of entire test through all interceptors)
        $listeners->addListener(TestPipelineStarting::class, $this->onTestPipelineStarting(...));
        $listeners->addListener(TestPipelineFinished::class, $this->onTestPipelineFinished(...));

        // Test Batch events (for DataProvider)
        $listeners->addListener(TestBatchStarting::class, $this->onTestBatchStarting(...));
        $listeners->addListener(TestBatchFinished::class, $this->onTestBatchFinished(...));

        // DataSet events (for individual datasets within DataProvider)
        $listeners->addListener(TestDataSetStarting::class, $this->onTestDataSetStarting(...));
        $listeners->addListener(TestDataSetFinished::class, $this->onTestDataSetFinished(...));

        // TestCase events
        $listeners->addListener(TestCaseStarting::class, $this->onTestCaseStarting(...));
        $listeners->addListener(TestCaseFinished::class, $this->onTestCaseFinished(...));

        // TestSuite events
        $listeners->addListener(TestSuiteStarting::class, $this->onTestSuiteStarting(...));
        $listeners->addListener(TestSuiteFinished::class, $this->onTestSuiteFinished(...));
    }

    private function onSessionStarting(SessionStarting $event): void
    {
        $this->logger->ensureHeader();
        $this->logger->printEnvironment();
    }

    private function onSessionFinished(SessionFinished $event): void
    {
        $this->logger->printSummary($event->result);
    }

    private function onMessageReceived(MessageReceived $event): void
    {
        $identity = $event->identity;
        if ($identity === null) {
            // Belongs to no test (suite/case setup, output between tests) — stream it ungrouped.
            $this->logger->logMessage($event->message);
            return;
        }

        // Name the test in the channel header only while several are in flight: then, and only then,
        // is a block ambiguous. A sequential run keeps the plain `[channel] time` header it always had,
        // and an unlabelled block means exactly one test was running when it opened.
        $this->logger->logMessage(
            $event->message,
            $identity->id,
            \count($this->running) > 1 ? ($this->running[$identity->id] ?? null) : null,
        );
    }

    private function onTestPipelineStarting(TestPipelineStarting $event): void
    {
        // Fresh channel grouping for the test about to run.
        $this->logger->resetChannels();
        $this->running[$event->testInfo->identity->id] = $event->testInfo->name;
    }

    private function onTestPipelineFinished(TestPipelineFinished $event): void
    {
        // Check if this test was inside a DataProvider batch
        $id = $event->testInfo->identity->id;
        unset($this->running[$id]);
        if (isset($this->isBatch[$id])) {
            // DataProvider test - already handled in dataset events
            unset($this->isBatch[$id]);
            return;
        }

        // Regular test without DataProvider - log it now
        $this->logger->testStartedFromInfo($event->testInfo);
        $duration = (int) $event->testResult->getAttribute('duration');
        $this->logger->handleTestResult($event->testResult, $duration);
    }

    private function onTestBatchStarting(TestBatchStarting $event): void
    {
        // Mark that we're inside a batch
        $id = $event->testInfo->identity->id;
        $this->isBatch[$id] = true;

        // Start batch in logger for proper indentation
        $this->logger->batchStartedFromInfo($event->testInfo);
    }

    private function onTestBatchFinished(TestBatchFinished $event): void
    {
        // Finish batch in logger
        $this->logger->batchFinishedFromInfo($event->testInfo);
    }

    private function onTestDataSetStarting(TestDataSetStarting $event): void
    {
        // Fresh channel grouping for the data set about to run.
        $this->logger->resetChannels();

        // Log individual dataset start with custom name
        $prefix = $event->providerIndex === null ? '' : "$event->providerIndex:";
        $datasetName = "Dataset #{$prefix}{$event->datasetIndex} [$event->dataSetKey]";
        $this->logger->testStartedFromInfo($event->testInfo, $datasetName);

        // Data sets of one provider share the batch's identity and run sequentially, so attributing the
        // batch's id to the data set currently streaming is unambiguous.
        $this->running[$event->testInfo->identity->id] = $datasetName;
    }

    private function onTestDataSetFinished(TestDataSetFinished $event): void
    {
        // Handle individual dataset result (name is already set in testStartedFromInfo)
        $duration = (int) $event->testResult->getAttribute('duration');
        $this->logger->handleTestResult($event->testResult, $duration);
    }

    private function onTestCaseStarting(TestCaseStarting $event): void
    {
        $this->logger->caseStartedFromInfo($event->caseInfo);
    }

    private function onTestCaseFinished(TestCaseFinished $event): void
    {
        $this->logger->handleCaseResult($event->caseInfo, $event->caseResult);
    }

    private function onTestSuiteStarting(TestSuiteStarting $event): void
    {
        $this->logger->suiteStartedFromInfo($event->suiteInfo);
    }

    private function onTestSuiteFinished(TestSuiteFinished $event): void
    {
        $this->logger->handleSuiteResult($event->suiteInfo, $event->suiteResult);
    }
}
