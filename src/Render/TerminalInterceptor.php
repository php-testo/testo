<?php

declare(strict_types=1);

namespace Testo\Render;

use Testo\Common\Container;
use Testo\Config\EventListenerCollector;
use Testo\Config\PluginConfigurator;
use Testo\Render\Terminal\ColorMode;
use Testo\Render\Terminal\Style;
use Testo\Render\Terminal\TerminalLogger;
use Testo\Test\Dto\TestInfo;
use Testo\Test\Event\Test\TestBatchFinished;
use Testo\Test\Event\Test\TestBatchStarting;
use Testo\Test\Event\Test\TestDataSetFinished;
use Testo\Test\Event\Test\TestDataSetStarting;
use Testo\Test\Event\Test\TestPipelineFinished;
use Testo\Test\Event\TestCase\TestCaseFinished;
use Testo\Test\Event\TestCase\TestCaseStarting;
use Testo\Test\Event\TestSuite\TestSuiteFinished;
use Testo\Test\Event\TestSuite\TestSuiteStarting;

/**
 * Terminal interceptor for rendering test results with configurable output.
 *
 * Implements StdoutRenderer to ensure only one stdout renderer is active.
 * Supports multiple output formats (Compact, Verbose, Dots) and color modes.
 */
final class TerminalInterceptor implements PluginConfigurator
{
    /**
     * Tracks whether we're inside a DataProvider batch.
     *
     * @var array<non-empty-string, bool>
     */
    private array $isBatch = [];

    public function __construct(
        private readonly TerminalLogger $logger,
        ColorMode $colorMode = ColorMode::Always,
    ) {
        // Configure color support based on mode
        Style::setColorsEnabled($colorMode->shouldUseColors());
    }

    public function configure(Container $container): void
    {
        $listeners = $container->get(EventListenerCollector::class);

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

    private static function getId(TestInfo $testInfo): string
    {
        return \spl_object_hash($testInfo->testDefinition);
    }

    private function onTestPipelineFinished(TestPipelineFinished $event): void
    {
        // Check if this test was inside a DataProvider batch
        $id = self::getId($event->testInfo);
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
        $id = self::getId($event->testInfo);
        $this->isBatch[$id] = true;
    }

    private function onTestBatchFinished(TestBatchFinished $event): void
    {
        // Batch finished - cleanup is done in TestPipelineFinished
    }

    private function onTestDataSetStarting(TestDataSetStarting $event): void
    {
        // Log individual dataset start
        $this->logger->testStartedFromInfo($event->testInfo);
    }

    private function onTestDataSetFinished(TestDataSetFinished $event): void
    {
        // Handle individual dataset result
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

        // Print final summary after all tests in suite complete
        $this->logger->printSummary();
    }
}
