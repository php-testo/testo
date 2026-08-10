<?php

declare(strict_types=1);

namespace Testo\Output\Teamcity;

use Internal\Container\Container;
use Testo\Common\EventListenerCollector;
use Testo\Common\Messenger;
use Testo\Common\PluginConfigurator;
use Testo\Core\Context\TestInfo;
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
use Testo\Output\Teamcity\Teamcity\TeamcityLogger;

final class TeamcityPlugin implements PluginConfigurator
{
    /**
     * Ids of the tests currently inside a DataProvider batch.
     *
     * Keyed by {@see \Testo\Core\Context\Identity\TestIdentity::$pipelineId} so concurrently running
     * tests are tracked independently — a single scalar would be clobbered the moment two tests
     * interleave — and so a batch's data sets, whose own runs differ, find the batch's entry.
     *
     * @var array<int, bool>
     */
    private array $isBatch = [];

    /**
     * Name of the in-flight test/data set that streamed {@see MessageReceived} output is attributed to,
     * per running test id. A test with no entry is not running (its output is dropped).
     *
     * @var array<int, non-empty-string>
     */
    private array $currentName = [];

    /**
     * Regular (non-DataProvider) tests whose `testStarted` has not been emitted yet, per test id. Emitted
     * lazily on the first message (so output streams in real time), or at pipeline finish if the test
     * produced no output. Dropped once `testStarted` has been emitted (or for data sets, which emit it
     * eagerly).
     *
     * @var array<int, TestInfo>
     */
    private array $pendingStart = [];

    private readonly TeamcityLogger $logger;

    public function __construct()
    {
        $this->logger = new TeamcityLogger();
    }

    #[\Override]
    public function configure(Container $container): void
    {
        $listeners = $container->get(EventListenerCollector::class);

        // Framework events
        $listeners->addListener(SessionStarting::class, $this->onSessionStarting(...));
        $listeners->addListener(SessionFinished::class, $this->onSessionFinished(...));

        // Messenger output — streamed in real time as stdout/stderr for the current test.
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

    /**
     * Clears one test's attribution once it is done, so later output outside any test is dropped.
     */
    private function resetCurrent(int $id): void
    {
        unset($this->currentName[$id], $this->pendingStart[$id]);
    }

    private function onSessionStarting(SessionStarting $event): void
    {
        $this->logger->logEnvironment();
    }

    private function onSessionFinished(SessionFinished $event): void
    {
        // An empty run verified nothing; surface it as a build problem so CI fails the build instead
        // of reporting a green, test-free success.
        $event->result->summary->total() === 0 and $this->logger->logEmptyRun();
    }

    private function onMessageReceived(MessageReceived $event): void
    {
        $identity = $event->identity;

        // No attributable test — the message belongs to none (suite/case setup, output between tests),
        // or its test is not tracked here. Drop it; internal errors on the dedicated stderr channel are
        // the exception and are surfaced as a standalone message instead.
        if ($identity === null || !isset($this->currentName[$identity->pipelineId])) {
            $event->message->channel === Messenger::CHANNEL_STDERR
                and $this->logger->logStandaloneMessage($event->message);
            return;
        }

        $id = $identity->pipelineId;

        // Lazily emit testStarted for a regular test on its first output, so it streams in real time.
        if (isset($this->pendingStart[$id])) {
            $this->logger->testStartedFromInfo($this->pendingStart[$id]);
            unset($this->pendingStart[$id]);
        }

        $this->logger->logMessage($this->currentName[$id], $event->message, $identity);
    }

    private function onTestPipelineStarting(TestPipelineStarting $event): void
    {
        // Assume a regular test: attribute output to it and keep testStarted pending until output
        // arrives. If it turns out to be a DataProvider batch, onTestBatchStarting clears this.
        $id = $event->testInfo->identity->pipelineId;
        $this->currentName[$id] = $event->testInfo->name;
        $this->pendingStart[$id] = $event->testInfo;
    }

    private function onTestPipelineFinished(TestPipelineFinished $event): void
    {
        // Check if this test was inside a DataProvider batch
        $id = $event->testInfo->identity->pipelineId;
        if (isset($this->isBatch[$id])) {
            // DataProvider test - already handled in batch events
            unset($this->isBatch[$id]);
            $this->resetCurrent($id);
            return;
        }

        // Regular test: testStarted was emitted lazily on first output; if there was none, emit now.
        if (isset($this->pendingStart[$id])) {
            $this->logger->testStartedFromInfo($this->pendingStart[$id]);
            unset($this->pendingStart[$id]);
        }

        $duration = (int) $event->testResult->getAttribute('duration');
        $this->logger->handleSingleTestResult($event->testResult, $duration);
        $this->resetCurrent($id);
    }

    private function onTestBatchStarting(TestBatchStarting $event): void
    {
        // Mark that we're inside a batch
        $id = $event->testInfo->identity->pipelineId;
        $this->isBatch[$id] = true;

        // It's a DataProvider, not a single test: drop the pending single-test start; data sets
        // emit their own testStarted and own the current attribution.
        $this->resetCurrent($id);

        // For DataProvider tests, start a test suite (wraps all data sets)
        $this->logger->batchStartedFromInfo($event->testInfo);
    }

    private function onTestBatchFinished(TestBatchFinished $event): void
    {
        // For DataProvider tests, close the test suite
        $this->logger->batchFinishedFromInfo($event->testInfo, $event->testResult->status);
    }

    private function onTestDataSetStarting(TestDataSetStarting $event): void
    {
        // Send testStarted for individual dataset within DataProvider
        $prefix = $event->providerIndex === null ? '' : "$event->providerIndex:";
        $name = "Dataset #{$prefix}{$event->datasetIndex} [$event->dataSetKey]";

        # The info's address already points at the data set, so the location hint carries the same
        # coordinates `--filter` takes.
        $this->logger->testStartedFromInfo($event->testInfo, overrideName: $name);

        // testStarted already emitted eagerly; stream this data set's output to it in real time. Data
        // sets of one batch share its run and go one at a time, so one current-name entry per run is
        // unambiguous — this one replaces the previous data set's.
        $id = $event->testInfo->identity->pipelineId;
        $this->currentName[$id] = $name;
        unset($this->pendingStart[$id]);
    }

    private function onTestDataSetFinished(TestDataSetFinished $event): void
    {
        // Handle individual dataset result
        $duration = (int) $event->testResult->getAttribute('duration');
        $prefix = $event->providerIndex === null ? '' : "$event->providerIndex:";
        $name = "Dataset #{$prefix}{$event->datasetIndex} [$event->datasetKey]";

        $this->logger->handleSingleTestResult($event->testResult, $duration, overrideName: $name);
        $this->resetCurrent($event->testInfo->identity->pipelineId);
    }

    private function onTestCaseStarting(TestCaseStarting $event): void
    {
        $this->logger->caseStartedFromInfo($event->caseInfo);
    }

    private function onTestCaseFinished(TestCaseFinished $event): void
    {
        $this->logger->handleCaseResult($event->caseInfo, $event->caseResult);

        // No test spans a case boundary, so anything still tracked belongs to a test the runner never
        // finished (a hang, an abort) — dropped here so a long session does not accumulate it.
        $this->isBatch = [];
        $this->currentName = [];
        $this->pendingStart = [];
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
