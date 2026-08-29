<?php

declare(strict_types=1);

namespace Testo\Output\JUnit;

use Internal\Container\Container;
use Internal\Path;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Common\EventListenerCollector;
use Testo\Common\PluginConfigurator;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\TestInfo;
use Testo\Core\Value\TestType;
use Testo\Event\Framework\SessionFinished;
use Testo\Event\Framework\SessionStarting;
use Testo\Core\Report\ReportInfo;
use Testo\Event\Report\ReportFileGenerated;
use Testo\Event\Report\ReportFileGenerating;
use Testo\Event\Test\TestBatchFinished;
use Testo\Event\Test\TestBatchStarting;
use Testo\Event\Test\TestDataSetFinished;
use Testo\Event\Test\TestPipelineFinished;
use Testo\Event\TestCase\TestCaseFinished;
use Testo\Event\TestCase\TestCaseStarting;
use Testo\Event\TestSuite\TestSuiteFinished;
use Testo\Event\TestSuite\TestSuiteStarting;
use Testo\Output\JUnit\Internal\JUnitInput;
use Testo\Output\JUnit\Internal\JUnitWriter;

/**
 * Plugin that emits a JUnit XML (PHPUnit dialect) report on session end.
 *
 * The XML is the de-facto interchange format for CI test reporters (GitHub
 * Actions, GitLab, Jenkins, Azure DevOps): adding this plugin to
 * `ApplicationConfig::plugins` lets those platforms render the test tab,
 * annotate failures on PR diffs, and track flaky tests without parsing
 * stdout.
 *
 * The conventional output filename is `junit.xml`; Infection in particular
 * expects it next to the `coverage-xml/` directory produced by
 * {@see \Testo\Codecov\Report\PhpUnitXmlReport} (e.g. `build/coverage/junit.xml`).
 *
 * # How activation works
 *
 * The plugin is already part of {@see \Testo\Application\Config\Plugin\ApplicationPlugins::defaults()},
 * so every project that doesn't replace the defaults gets one **inert** copy
 * for free. Inert means: no listeners are registered and no file is written.
 * The inert default exists so the `--log-junit=<path>` CLI flag has something to
 * activate without any change to `testo.php`.
 *
 * Activation rules:
 * - Constructor path is set → the plugin always writes to that path; the
 *   `--log-junit` flag is ignored. Use this when you want JUnit output as part
 *   of every run (e.g. on CI by default).
 * - Constructor path is null → the plugin reads the `--log-junit=<path>` flag
 *   on each run; if the flag is absent it stays inert. This is the mode
 *   used by the default inert instance and by Infection (which passes the
 *   path it expects via the flag).
 *
 * Multiple instances are fully independent — each one runs through the rules
 * above on its own. A plugin instance with a constructor path will produce
 * its file on **every** run, regardless of whether `--log-junit=…` was passed.
 * This means manually-added instances write **additional** report files,
 * they do not replace the default one. So this config:
 *
 * ```
 * ApplicationPlugins::with(new JUnitPlugin('/always.xml'))
 * ```
 *
 * yields one report at `/always.xml` on every run; if the same run is also
 * invoked with `--log-junit=/from-cli.xml`, the default inert instance writes
 * a second report at `/from-cli.xml` — both files end up on disk.
 *
 * To pin the report to a fixed path and prevent the CLI flag from creating
 * a second file, drop the default first:
 * `ApplicationPlugins::without(JUnitPlugin::class)->with(new JUnitPlugin('/always.xml'))`.
 *
 * @api
 */
final class JUnitPlugin implements PluginConfigurator
{
    /**
     * Tracks whether we're inside a DataProvider batch, keyed by
     * {@see \Testo\Core\Context\Identity\TestIdentity::$pipelineId}. Same guard `TeamcityPlugin`
     * uses to avoid emitting both the per-dataset and the rolled-up `<testcase>`;
     * keyed per running test, so concurrently running tests are tracked independently.
     *
     * @var array<int, bool>
     */
    private array $isBatch = [];

    /**
     * Output path. Seeded from the constructor argument and, if that was
     * absent, from the `--log-junit=<path>` CLI flag in {@see configure()}.
     * Stays null when neither source provided a path — in that case the
     * plugin remains inert.
     */
    private ?Path $resolvedPath;

    /**
     * Single-shot guard so the plugin attaches its listeners only to the
     * outer dispatcher when the same instance is shared across containers.
     * `ApplicationPlugins::defaults()` reuses one `JUnitPlugin` for every
     * top-level run AND for every nested {@see \Testo\Testing\Helper\TestRunner::runTest()}
     * sub-run; without this guard inner `SessionStarting` would clear the
     * outer writer state, and inner `SessionFinished` would overwrite the
     * file with inner-only suites.
     */
    private bool $configured = false;

    private readonly JUnitWriter $writer;

    /** @var list<non-empty-string> */
    private readonly array $testTypes;

    /**
     * @param non-empty-string|null $outputPath Where to write the JUnit XML.
     *        When set, the plugin always writes to this path. When null, the
     *        plugin falls back to the `--log-junit=<path>` CLI flag, and stays
     *        inert if the flag is also absent.
     * @param non-empty-string $rootName Value of the `name` attribute on the
     *        root `<testsuites>` element. CI reporters display it as the title
     *        of the run; useful for distinguishing multiple JUnit reports
     *        produced by the same pipeline (e.g. `'Unit'` vs `'Integration'`).
     * @param list<non-empty-string|\BackedEnum> $testTypes Test case types to include in
     *        the report. Cases of any other type are skipped entirely — no
     *        `<testsuite>`/`<testcase>` element is emitted for them. Empty array means
     *        all types. Use {@see TestType} cases or custom string identifiers.
     *        Defaults to {@see TestType::Test} and {@see TestType::TestInline}, deliberately
     *        excluding bench/profile cases: the primary JUnit consumers (Infection, CI test
     *        reporters) look up class-bound test methods by FQN and those cases would pollute
     *        that mapping.
     */
    public function __construct(
        ?string $outputPath = null,
        private readonly string $rootName = 'Testo',
        array $testTypes = [TestType::Test, TestType::TestInline],
    ) {
        $this->resolvedPath = $outputPath !== null && $outputPath !== ''
            ? Path::create($outputPath)
            : null;
        $this->testTypes = \array_map(
            static fn(string|\BackedEnum $t): string => $t instanceof \BackedEnum ? $t->value : $t,
            $testTypes,
        );
        $this->writer = new JUnitWriter();
    }

    #[\Override]
    public function configure(Container $container): void
    {
        if ($this->configured) {
            return;
        }
        $this->configured = true;

        // Constructor path wins. CLI flag is consulted only when no explicit
        // path was passed to the constructor — that's how the inert default
        // instance in ApplicationPlugins::defaults() gets activated.
        if ($this->resolvedPath === null) {
            $cliPath = $container->get(JUnitInput::class)->outputPath;
            if ($cliPath === null || $cliPath === '') {
                return;
            }
            $this->resolvedPath = Path::create($cliPath);
        }

        $listeners = $container->get(EventListenerCollector::class);

        // Framework events
        $listeners->addListener(SessionStarting::class, $this->onSessionStarting(...));
        $listeners->addListener(SessionFinished::class, $this->onSessionFinished(...));

        // TestSuite events
        $listeners->addListener(TestSuiteStarting::class, $this->onTestSuiteStarting(...));
        $listeners->addListener(TestSuiteFinished::class, $this->onTestSuiteFinished(...));

        // TestCase events
        $listeners->addListener(TestCaseStarting::class, $this->onTestCaseStarting(...));
        $listeners->addListener(TestCaseFinished::class, $this->onTestCaseFinished(...));

        // Test Batch events (for DataProvider)
        $listeners->addListener(TestBatchStarting::class, $this->onTestBatchStarting(...));
        $listeners->addListener(TestBatchFinished::class, $this->onTestBatchFinished(...));

        // DataSet events (for individual datasets within DataProvider)
        $listeners->addListener(TestDataSetFinished::class, $this->onTestDataSetFinished(...));

        // Test Pipeline events (final event in the test lifecycle)
        $listeners->addListener(TestPipelineFinished::class, $this->onTestPipelineFinished(...));

        // Registered after the listener that writes the file, so the late announcement follows the write.
        $info = new ReportInfo('junit', 'JUnit report', $this->resolvedPath);
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

    private static function formatDatasetSuffix(string|int $datasetKey, ?int $providerIndex): string
    {
        return $providerIndex === null
            ? (string) $datasetKey
            : "{$providerIndex}:{$datasetKey}";
    }

    /**
     * Drops cases whose type isn't on the allow-list. Filtering at the case level is
     * sufficient because all events for tests inside a case (batch, dataset, pipeline)
     * share the same `caseInfo->definition->type`.
     */
    private function isFilteredOut(CaseInfo $caseInfo): bool
    {
        return $this->testTypes !== [] && !\in_array($caseInfo->definition->type, $this->testTypes, true);
    }

    private function onSessionStarting(SessionStarting $event): void
    {
        $this->writer->reset();
        $this->isBatch = [];
    }

    private function onSessionFinished(SessionFinished $event): void
    {
        \assert($this->resolvedPath !== null);
        $this->writer->write($this->resolvedPath, $this->rootName);
    }

    private function onTestSuiteStarting(TestSuiteStarting $event): void
    {
        $this->writer->startSuite($event->suiteInfo->name);
    }

    private function onTestSuiteFinished(TestSuiteFinished $event): void
    {
        $this->writer->finishSuite();
    }

    private function onTestCaseStarting(TestCaseStarting $event): void
    {
        $caseInfo = $event->caseInfo;
        if ($this->isFilteredOut($caseInfo)) {
            return;
        }

        // For class-bound cases, emit the bare FQN as the suite name (no
        // `[type]` suffix) and tag it with the class file. This matches
        // PHPUnit's JUnit shape and is what Infection's `JUnitTestFileDataProvider`
        // looks up via `//testsuite[@name="FQN"]`.
        //
        // Free-function cases (no class reflection) wrap a whole file that may
        // contain several test functions; a case-level suite keyed on the file
        // basename can't satisfy Infection's per-function FQN lookup. We skip
        // the case-level <testsuite> here and open a per-function synthetic
        // suite around each test result instead — see openFunctionSuite().
        $reflection = $caseInfo->definition->reflection;
        if ($reflection === null) {
            return;
        }

        $name = $reflection->getName();
        \assert($name !== '');

        $file = $reflection->getFileName();
        $file = ($file === false || $file === '') ? null : $file;

        $this->writer->startSuite($name, $file);
    }

    private function onTestCaseFinished(TestCaseFinished $event): void
    {
        if ($this->isFilteredOut($event->caseInfo)) {
            return;
        }

        if ($event->caseInfo->definition->reflection === null) {
            return;
        }

        $this->writer->finishSuite();
    }

    private function onTestBatchStarting(TestBatchStarting $event): void
    {
        $caseInfo = $event->testInfo->caseInfo;
        if ($this->isFilteredOut($caseInfo)) {
            return;
        }

        // Free-function batch (DataProvider/multi-#[TestInline]): open a
        // per-function suite that all dataset rows of this batch will land in.
        // Closed in onTestBatchFinished.
        $caseInfo->definition->reflection === null and $this->openFunctionSuite($event->testInfo);

        $this->isBatch[$event->testInfo->identity->pipelineId] = true;
    }

    private function onTestBatchFinished(TestBatchFinished $event): void
    {
        $caseInfo = $event->testInfo->caseInfo;
        if ($this->isFilteredOut($caseInfo)) {
            return;
        }

        // Counterpart to the per-function suite opened in onTestBatchStarting.
        // Marker for the dataset/pipeline split is cleared in TestPipelineFinished.
        $caseInfo->definition->reflection === null and $this->writer->finishSuite();
    }

    private function onTestDataSetFinished(TestDataSetFinished $event): void
    {
        if ($this->isFilteredOut($event->testInfo->caseInfo)) {
            return;
        }

        $name = $event->testResult->info->name . ' [' . self::formatDatasetSuffix($event->datasetKey, $event->providerIndex) . ']';
        \assert($name !== '');

        // Stamp provider/dataset coordinates onto the <testcase> via Testo's
        // private namespace so the bridge can recover them without re-deriving
        // from the human-readable name suffix.
        $this->writer->addTestResult(
            $event->testResult,
            $name,
            providerIndex: $event->providerIndex,
            datasetIndex: $event->datasetIndex,
            datasetKey: $event->datasetKey,
        );
    }

    private function onTestPipelineFinished(TestPipelineFinished $event): void
    {
        if ($this->isFilteredOut($event->testInfo->caseInfo)) {
            return;
        }

        $id = $event->testInfo->identity->pipelineId;
        if (isset($this->isBatch[$id])) {
            // DataProvider/multi-inline test — individual datasets were already emitted.
            unset($this->isBatch[$id]);
            return;
        }

        // Free-function single-shot test: wrap the testcase in a per-function
        // synthetic <testsuite> so Infection's `//testsuite[@name="FQN"]` lookup
        // resolves. Class-bound tests already sit inside their case-level FQN suite.
        if ($event->testInfo->caseInfo->definition->reflection === null) {
            $this->openFunctionSuite($event->testInfo);
            $this->writer->addTestResult($event->testResult);
            $this->writer->finishSuite();
            return;
        }

        $this->writer->addTestResult($event->testResult);
    }

    /**
     * Opens a synthetic <testsuite> named after the test function's FQN, with
     * its source file attached. Used for free-function cases so Infection can
     * resolve `<covered by="Function\Fqn">` entries from the coverage XML back
     * to a test file via `//testsuite[@name="Function\Fqn"]`.
     */
    private function openFunctionSuite(TestInfo $testInfo): void
    {
        $reflection = $testInfo->testDefinition->reflection;
        $name = $reflection->getName();
        \assert($name !== '');

        $file = $reflection->getFileName();
        $file = ($file === false || $file === '') ? null : $file;

        $this->writer->startSuite($name, $file);
    }
}
