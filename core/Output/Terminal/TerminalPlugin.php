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
     * Keyed by {@see \Testo\Core\Context\Identity\TestIdentity::$pipelineId} so concurrently running
     * tests are tracked independently.
     *
     * @var array<int, bool>
     */
    private array $isBatch = [];

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
        // A null identity means the message belongs to no test (suite/case setup, output between
        // tests); the logger writes that through instead of putting it in anyone's block.
        $this->logger->logMessage($event->message, $event->identity?->pipelineId);
    }

    private function onTestPipelineFinished(TestPipelineFinished $event): void
    {
        // Check if this test was inside a DataProvider batch
        $id = $event->testInfo->identity->pipelineId;
        if (isset($this->isBatch[$id])) {
            // DataProvider test - already handled in dataset events
            unset($this->isBatch[$id]);
            $this->logger->closeTest($event->testInfo);
            return;
        }

        // Regular test without DataProvider - log it now
        $this->logger->testStartedFromInfo($event->testInfo);
        $duration = (int) $event->testResult->getAttribute('duration');
        $this->logger->handleTestResult($event->testResult, $duration);

        // Last write for this test is done, so its block can go out.
        $this->logger->closeTest($event->testInfo);
    }

    private function onTestBatchStarting(TestBatchStarting $event): void
    {
        // Mark that we're inside a batch
        $id = $event->testInfo->identity->pipelineId;
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
        // Data sets share the batch's block, so nothing else would separate their channel output —
        // reset the grouping so this one opens with a fresh header.
        $this->logger->resetChannels($event->testInfo);

        // Log individual dataset start with custom name
        $prefix = $event->providerIndex === null ? '' : "$event->providerIndex:";
        $datasetName = "Dataset #{$prefix}{$event->datasetIndex} [$event->dataSetKey]";
        $this->logger->testStartedFromInfo($event->testInfo, $datasetName);
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

        // No test spans a case boundary: a leftover entry belongs to a test that never finished.
        $this->isBatch = [];
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
