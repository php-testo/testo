<?php

declare(strict_types=1);

namespace Tests\Output\Unit\JUnit;

use Internal\Path;
use Internal\Container\ObjectContainer;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Internal\EventDispatcher;
use Testo\Assert;
use Testo\Common\EventListenerCollector;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\RunResult;
use Testo\Core\Context\SuiteInfo;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;
use Testo\Event\Framework\SessionFinished;
use Testo\Event\Framework\SessionStarting;
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
use Testo\Output\JUnit\JUnitPlugin;
use Testo\Test;
use Tests\Output\Stub\JUnit\SampleTestClass;

#[Test]
final class JUnitPluginTest
{
    public function writesXmlOnSessionFinished(): void
    {
        // Arrange
        $path = self::tmpPath();
        try {
            $dispatcher = self::wirePlugin(new JUnitPlugin($path, 'Testo'));

            // Act — minimal lifecycle: session start/end with no tests.
            $dispatcher->dispatch(new SessionStarting());
            $dispatcher->dispatch(self::sessionFinished());

            // Assert
            Assert::true(\file_exists($path));
            $xml = \simplexml_load_file($path);
            Assert::notSame($xml, false);
            Assert::same((string) $xml['name'], 'Testo');
            Assert::same((string) $xml['tests'], '0');
        } finally {
            self::cleanup($path);
        }
    }

    public function emitsCaseForRegularPipelineTest(): void
    {
        // Arrange
        $path = self::tmpPath();
        try {
            $dispatcher = self::wirePlugin(new JUnitPlugin($path));

            $suiteInfo = self::makeSuiteInfo('CoreSuite');
            $caseInfo = self::makeCaseInfo();
            $testInfo = self::makeTestInfo('passingTest');
            $result = new TestResult(info: $testInfo, status: Status::Passed, attributes: ['duration' => 7]);

            // Act
            $dispatcher->dispatch(new SessionStarting());
            $dispatcher->dispatch(new TestSuiteStarting($suiteInfo));
            $dispatcher->dispatch(new TestCaseStarting($caseInfo));
            $dispatcher->dispatch(new TestPipelineFinished($testInfo, $result));
            $dispatcher->dispatch(new TestCaseFinished($caseInfo, new CaseResult([$result], Status::Passed)));
            $dispatcher->dispatch(new TestSuiteFinished($suiteInfo, new SuiteResult([], Status::Passed)));
            $dispatcher->dispatch(self::sessionFinished());

            // Assert
            $xml = \simplexml_load_file($path);
            Assert::notSame($xml, false);
            Assert::same((string) $xml['tests'], '1');
            Assert::same((string) $xml->testsuite['name'], 'CoreSuite');
            // Class-layer suite carries the bare FQN (Infection-compatible),
            // not `caseInfo->name` which has the `[type]` suffix.
            Assert::same((string) $xml->testsuite->testsuite['name'], SampleTestClass::class);
            Assert::same((string) $xml->testsuite->testsuite['file'], (new \ReflectionClass(SampleTestClass::class))->getFileName());
            Assert::same((string) $xml->testsuite->testsuite->testcase['name'], 'passingTest');
        } finally {
            self::cleanup($path);
        }
    }

    public function dataProviderEmitsCasePerDataSetAndSuppressesPipelineCase(): void
    {
        // Arrange
        $path = self::tmpPath();
        try {
            $dispatcher = self::wirePlugin(new JUnitPlugin($path));

            $suiteInfo = self::makeSuiteInfo('CoreSuite');
            $caseInfo = self::makeCaseInfo();
            $testInfo = self::makeTestInfo('passingTest');

            $datasetA = new TestResult(info: $testInfo, status: Status::Passed, attributes: ['duration' => 1]);
            $datasetB = new TestResult(info: $testInfo, status: Status::Passed, attributes: ['duration' => 2]);
            $aggregate = new TestResult(info: $testInfo, status: Status::Passed);

            // Act — TestBatchStarting marks the test as a data-provider parent;
            // TestPipelineFinished must skip emission so we don't double-count.
            $dispatcher->dispatch(new SessionStarting());
            $dispatcher->dispatch(new TestSuiteStarting($suiteInfo));
            $dispatcher->dispatch(new TestCaseStarting($caseInfo));
            $dispatcher->dispatch(new TestBatchStarting($testInfo));
            $dispatcher->dispatch(new TestDataSetFinished($testInfo, $datasetA, 'alpha', null, 0));
            $dispatcher->dispatch(new TestDataSetFinished($testInfo, $datasetB, 'beta', null, 1));
            $dispatcher->dispatch(new TestBatchFinished($testInfo, $aggregate));
            $dispatcher->dispatch(new TestPipelineFinished($testInfo, $aggregate));
            $dispatcher->dispatch(new TestCaseFinished($caseInfo, new CaseResult([$aggregate], Status::Passed)));
            $dispatcher->dispatch(new TestSuiteFinished($suiteInfo, new SuiteResult([], Status::Passed)));
            $dispatcher->dispatch(self::sessionFinished());

            // Assert — exactly two cases, no rolled-up duplicate.
            $xml = \simplexml_load_file($path);
            Assert::notSame($xml, false);
            Assert::same((string) $xml['tests'], '2');
            $cases = $xml->testsuite->testsuite->testcase;
            Assert::count($cases, 2);
            Assert::same((string) $cases[0]['name'], 'passingTest [alpha]');
            Assert::same((string) $cases[1]['name'], 'passingTest [beta]');
        } finally {
            self::cleanup($path);
        }
    }

    public function interleavedBatchesSharingADefinitionDoNotClobberEachOther(): void
    {
        $path = self::tmpPath();
        try {
            $dispatcher = self::wirePlugin(new JUnitPlugin($path));

            $suiteInfo = self::makeSuiteInfo('CoreSuite');
            $caseInfo = self::makeCaseInfo();

            // Two runs of one test method: distinct in-flight tests, one shared TestDefinition object.
            // The batch guard is keyed per running test, so one pipeline's finish must not clear the
            // other's marker.
            $definition = new TestDefinition(new \ReflectionMethod(SampleTestClass::class, 'passingTest'));
            $first = new TestInfo(name: 'passingTest', caseInfo: $caseInfo, testDefinition: $definition);
            $second = new TestInfo(name: 'passingTest', caseInfo: $caseInfo, testDefinition: $definition);

            $firstResult = new TestResult(info: $first, status: Status::Passed);
            $secondResult = new TestResult(info: $second, status: Status::Passed);

            $dispatcher->dispatch(new SessionStarting());
            $dispatcher->dispatch(new TestSuiteStarting($suiteInfo));
            $dispatcher->dispatch(new TestCaseStarting($caseInfo));
            $dispatcher->dispatch(new TestBatchStarting($first));
            $dispatcher->dispatch(new TestBatchStarting($second));
            $dispatcher->dispatch(new TestDataSetFinished($first, $firstResult, 'alpha', null, 0));
            $dispatcher->dispatch(new TestDataSetFinished($second, $secondResult, 'beta', null, 0));
            $dispatcher->dispatch(new TestBatchFinished($first, $firstResult));
            $dispatcher->dispatch(new TestBatchFinished($second, $secondResult));
            $dispatcher->dispatch(new TestPipelineFinished($first, $firstResult));
            $dispatcher->dispatch(new TestPipelineFinished($second, $secondResult));
            $dispatcher->dispatch(new TestCaseFinished($caseInfo, new CaseResult([$firstResult], Status::Passed)));
            $dispatcher->dispatch(new TestSuiteFinished($suiteInfo, new SuiteResult([], Status::Passed)));
            $dispatcher->dispatch(self::sessionFinished());

            // One <testcase> per data set and no rolled-up duplicate for either batch.
            $xml = \simplexml_load_file($path);
            Assert::notSame($xml, false);
            Assert::same((string) $xml['tests'], '2');
            $cases = $xml->testsuite->testsuite->testcase;
            Assert::count($cases, 2);
            Assert::same((string) $cases[0]['name'], 'passingTest [alpha]');
            Assert::same((string) $cases[1]['name'], 'passingTest [beta]');
        } finally {
            self::cleanup($path);
        }
    }

    public function multipleProvidersIncludeProviderIndexInName(): void
    {
        // Arrange
        $path = self::tmpPath();
        try {
            $dispatcher = self::wirePlugin(new JUnitPlugin($path));

            $suiteInfo = self::makeSuiteInfo('CoreSuite');
            $caseInfo = self::makeCaseInfo();
            $testInfo = self::makeTestInfo('passingTest');
            $result = new TestResult(info: $testInfo, status: Status::Passed);
            $aggregate = new TestResult(info: $testInfo, status: Status::Passed);

            // Act
            $dispatcher->dispatch(new SessionStarting());
            $dispatcher->dispatch(new TestSuiteStarting($suiteInfo));
            $dispatcher->dispatch(new TestCaseStarting($caseInfo));
            $dispatcher->dispatch(new TestBatchStarting($testInfo));
            $dispatcher->dispatch(new TestDataSetFinished($testInfo, $result, 'k', 0, 0));
            $dispatcher->dispatch(new TestDataSetFinished($testInfo, $result, 'k', 1, 1));
            $dispatcher->dispatch(new TestBatchFinished($testInfo, $aggregate));
            $dispatcher->dispatch(new TestPipelineFinished($testInfo, $aggregate));
            $dispatcher->dispatch(new TestCaseFinished($caseInfo, new CaseResult([$aggregate], Status::Passed)));
            $dispatcher->dispatch(new TestSuiteFinished($suiteInfo, new SuiteResult([], Status::Passed)));
            $dispatcher->dispatch(self::sessionFinished());

            // Assert
            $xml = \simplexml_load_file($path);
            Assert::notSame($xml, false);
            $cases = $xml->testsuite->testsuite->testcase;
            Assert::same((string) $cases[0]['name'], 'passingTest [0:k]');
            Assert::same((string) $cases[1]['name'], 'passingTest [1:k]');
        } finally {
            self::cleanup($path);
        }
    }

    public function dataSetRowsCarryTestoNamespacedCoordinates(): void
    {
        // Arrange — DataProvider-style batch with explicit provider indices.
        // Each <testcase> row must expose its provider/dataset coordinates so
        // the Infection bridge can build a `Class::method:provider:dataset`
        // CLI filter without re-parsing the human-readable name suffix.
        $path = self::tmpPath();
        try {
            $dispatcher = self::wirePlugin(new JUnitPlugin($path));

            $suiteInfo = self::makeSuiteInfo('CoreSuite');
            $caseInfo = self::makeCaseInfo();
            $testInfo = self::makeTestInfo('passingTest');
            $result = new TestResult(info: $testInfo, status: Status::Passed);
            $aggregate = new TestResult(info: $testInfo, status: Status::Passed);

            // Act
            $dispatcher->dispatch(new SessionStarting());
            $dispatcher->dispatch(new TestSuiteStarting($suiteInfo));
            $dispatcher->dispatch(new TestCaseStarting($caseInfo));
            $dispatcher->dispatch(new TestBatchStarting($testInfo));
            // providerIndex set (multi-provider DataProvider).
            $dispatcher->dispatch(new TestDataSetFinished($testInfo, $result, 'alpha', 0, 0));
            $dispatcher->dispatch(new TestDataSetFinished($testInfo, $result, 'beta', 1, 3));
            $dispatcher->dispatch(new TestBatchFinished($testInfo, $aggregate));
            $dispatcher->dispatch(new TestPipelineFinished($testInfo, $aggregate));
            $dispatcher->dispatch(new TestCaseFinished($caseInfo, new CaseResult([$aggregate], Status::Passed)));
            $dispatcher->dispatch(new TestSuiteFinished($suiteInfo, new SuiteResult([], Status::Passed)));
            $dispatcher->dispatch(self::sessionFinished());

            // Assert — namespace declared on root, attributes on each row,
            // values match the dispatched indices/keys.
            $dom = new \DOMDocument();
            Assert::true($dom->load($path));

            $ns = JUnitWriter::TESTO_NS;
            Assert::same($dom->documentElement->getAttribute('xmlns:testo'), $ns);

            $cases = $dom->getElementsByTagName('testcase');
            Assert::same($cases->length, 2);
            $first = $cases->item(0);
            Assert::same($first->getAttributeNS($ns, 'data-provider'), '0');
            Assert::same($first->getAttributeNS($ns, 'data-set'), '0');
            Assert::same($first->getAttributeNS($ns, 'data-set-key'), 'alpha');

            $second = $cases->item(1);
            Assert::same($second->getAttributeNS($ns, 'data-provider'), '1');
            Assert::same($second->getAttributeNS($ns, 'data-set'), '3');
            Assert::same($second->getAttributeNS($ns, 'data-set-key'), 'beta');
        } finally {
            self::cleanup($path);
        }
    }

    public function singleProviderDatasetNormalisesProviderIndexToZero(): void
    {
        // Arrange — `InlineInterceptor` and similar single-provider batches
        // dispatch TestDataSetFinished with providerIndex === null. The writer
        // must normalise that to "0" on emit so the (provider-index, dataset-index)
        // pair on the testcase is always usable as a CLI filter `:0:N` directly.
        $path = self::tmpPath();
        try {
            $dispatcher = self::wirePlugin(new JUnitPlugin($path));

            $suiteInfo = self::makeSuiteInfo('CoreSuite');
            $caseInfo = self::makeCaseInfo();
            $testInfo = self::makeTestInfo('passingTest');
            $result = new TestResult(info: $testInfo, status: Status::Passed);
            $aggregate = new TestResult(info: $testInfo, status: Status::Passed);

            // Act — null providerIndex emulates InlineInterceptor's dispatch.
            $dispatcher->dispatch(new SessionStarting());
            $dispatcher->dispatch(new TestSuiteStarting($suiteInfo));
            $dispatcher->dispatch(new TestCaseStarting($caseInfo));
            $dispatcher->dispatch(new TestBatchStarting($testInfo));
            $dispatcher->dispatch(new TestDataSetFinished($testInfo, $result, '2', null, 2));
            $dispatcher->dispatch(new TestBatchFinished($testInfo, $aggregate));
            $dispatcher->dispatch(new TestPipelineFinished($testInfo, $aggregate));
            $dispatcher->dispatch(new TestCaseFinished($caseInfo, new CaseResult([$aggregate], Status::Passed)));
            $dispatcher->dispatch(new TestSuiteFinished($suiteInfo, new SuiteResult([], Status::Passed)));
            $dispatcher->dispatch(self::sessionFinished());

            // Assert
            $dom = new \DOMDocument();
            Assert::true($dom->load($path));
            $case = $dom->getElementsByTagName('testcase')->item(0);
            Assert::same($case->getAttributeNS(JUnitWriter::TESTO_NS, 'data-provider'), '0');
            Assert::same($case->getAttributeNS(JUnitWriter::TESTO_NS, 'data-set'), '2');
        } finally {
            self::cleanup($path);
        }
    }

    public function regularPipelineTestcaseHasNoTestoAttributes(): void
    {
        // Arrange — non-dataset rows must stay clean: no testo:* attributes
        // so the bridge can rely on their presence as a "this is a dataset row"
        // signal.
        $path = self::tmpPath();
        try {
            $dispatcher = self::wirePlugin(new JUnitPlugin($path));

            $suiteInfo = self::makeSuiteInfo('CoreSuite');
            $caseInfo = self::makeCaseInfo();
            $testInfo = self::makeTestInfo('passingTest');
            $result = new TestResult(info: $testInfo, status: Status::Passed);

            // Act
            $dispatcher->dispatch(new SessionStarting());
            $dispatcher->dispatch(new TestSuiteStarting($suiteInfo));
            $dispatcher->dispatch(new TestCaseStarting($caseInfo));
            $dispatcher->dispatch(new TestPipelineFinished($testInfo, $result));
            $dispatcher->dispatch(new TestCaseFinished($caseInfo, new CaseResult([$result], Status::Passed)));
            $dispatcher->dispatch(new TestSuiteFinished($suiteInfo, new SuiteResult([], Status::Passed)));
            $dispatcher->dispatch(self::sessionFinished());

            // Assert
            $dom = new \DOMDocument();
            Assert::true($dom->load($path));
            $case = $dom->getElementsByTagName('testcase')->item(0);
            Assert::false($case->hasAttributeNS(JUnitWriter::TESTO_NS, 'data-provider'));
            Assert::false($case->hasAttributeNS(JUnitWriter::TESTO_NS, 'data-set'));
            Assert::false($case->hasAttributeNS(JUnitWriter::TESTO_NS, 'data-set-key'));
        } finally {
            self::cleanup($path);
        }
    }

    public function freeFunctionEmitsPerFunctionSuiteWithFqnName(): void
    {
        // Arrange — free-function case (no class reflection). The case wraps a
        // file that may contain several functions, so the case-level <testsuite>
        // must NOT be the level Infection's `//testsuite[@name="FQN"]` lookup
        // resolves against. The plugin instead opens a per-function synthetic
        // suite around each test result.
        require_once __DIR__ . '/../../Stub/JUnit/free_function_helper.php';
        $functionFqn = 'Tests\\Output\\Stub\\JUnit\\junitFreeFunction';
        $functionFile = (new \ReflectionFunction($functionFqn))->getFileName();

        $path = self::tmpPath();
        try {
            $dispatcher = self::wirePlugin(new JUnitPlugin($path, testTypes: []));

            $suiteInfo = self::makeSuiteInfo('CoreSuite');
            $caseInfo = self::makeFreeFunctionCaseInfo();
            $testInfo = self::makeFreeFunctionTestInfo($caseInfo, $functionFqn);
            $result = new TestResult(info: $testInfo, status: Status::Passed, attributes: ['duration' => 3]);

            // Act — full lifecycle of a single (non-batch) free-function test.
            $dispatcher->dispatch(new SessionStarting());
            $dispatcher->dispatch(new TestSuiteStarting($suiteInfo));
            $dispatcher->dispatch(new TestCaseStarting($caseInfo));
            $dispatcher->dispatch(new TestPipelineFinished($testInfo, $result));
            $dispatcher->dispatch(new TestCaseFinished($caseInfo, new CaseResult([$result], Status::Passed)));
            $dispatcher->dispatch(new TestSuiteFinished($suiteInfo, new SuiteResult([], Status::Passed)));
            $dispatcher->dispatch(self::sessionFinished());

            // Assert — outer plugin suite contains a per-function synthetic
            // suite (not the case-level "filename [type]" suite); its name is
            // the function FQN and its file is the function's source.
            $xml = \simplexml_load_file($path);
            Assert::notSame($xml, false);
            Assert::same((string) $xml->testsuite['name'], 'CoreSuite');
            $functionSuite = $xml->testsuite->testsuite;
            Assert::same((string) $functionSuite['name'], $functionFqn);
            Assert::same((string) $functionSuite['file'], $functionFile);
            // Single testcase inside; classname mirrors the suite name.
            Assert::count($functionSuite->testcase, 1);
            Assert::same((string) $functionSuite->testcase['classname'], $functionFqn);
        } finally {
            self::cleanup($path);
        }
    }

    public function freeFunctionBatchSharesPerFunctionSuiteAcrossDataSets(): void
    {
        // Arrange — multi-#[TestInline] / DataProvider on a free function.
        // All dataset rows must land under the same per-function <testsuite>,
        // opened on TestBatchStarting and closed on TestBatchFinished.
        require_once __DIR__ . '/../../Stub/JUnit/free_function_helper.php';
        $functionFqn = 'Tests\\Output\\Stub\\JUnit\\junitFreeFunction';

        $path = self::tmpPath();
        try {
            $dispatcher = self::wirePlugin(new JUnitPlugin($path, testTypes: []));

            $suiteInfo = self::makeSuiteInfo('CoreSuite');
            $caseInfo = self::makeFreeFunctionCaseInfo();
            $testInfo = self::makeFreeFunctionTestInfo($caseInfo, $functionFqn);
            $datasetA = new TestResult(info: $testInfo, status: Status::Passed, attributes: ['duration' => 1]);
            $datasetB = new TestResult(info: $testInfo, status: Status::Passed, attributes: ['duration' => 2]);
            $aggregate = new TestResult(info: $testInfo, status: Status::Passed);

            // Act
            $dispatcher->dispatch(new SessionStarting());
            $dispatcher->dispatch(new TestSuiteStarting($suiteInfo));
            $dispatcher->dispatch(new TestCaseStarting($caseInfo));
            $dispatcher->dispatch(new TestBatchStarting($testInfo));
            $dispatcher->dispatch(new TestDataSetFinished($testInfo, $datasetA, '0', null, 0));
            $dispatcher->dispatch(new TestDataSetFinished($testInfo, $datasetB, '1', null, 1));
            $dispatcher->dispatch(new TestBatchFinished($testInfo, $aggregate));
            $dispatcher->dispatch(new TestPipelineFinished($testInfo, $aggregate));
            $dispatcher->dispatch(new TestCaseFinished($caseInfo, new CaseResult([$aggregate], Status::Passed)));
            $dispatcher->dispatch(new TestSuiteFinished($suiteInfo, new SuiteResult([], Status::Passed)));
            $dispatcher->dispatch(self::sessionFinished());

            // Assert — exactly one function-level suite, both dataset rows inside.
            $xml = \simplexml_load_file($path);
            Assert::notSame($xml, false);
            Assert::count($xml->testsuite->testsuite, 1);
            $functionSuite = $xml->testsuite->testsuite;
            Assert::same((string) $functionSuite['name'], $functionFqn);
            Assert::count($functionSuite->testcase, 2);
        } finally {
            self::cleanup($path);
        }
    }

    public function constructorPathWinsOverCliFlag(): void
    {
        // Arrange — manually-added instances must obey their explicit path,
        // ignoring `--log-junit=…` (which is intended for the default inert instance).
        $constructorPath = self::tmpPath();
        $cliPath = self::tmpPath();
        try {
            $dispatcher = self::wirePlugin(new JUnitPlugin($constructorPath), cliPath: $cliPath);

            // Act
            $dispatcher->dispatch(new SessionStarting());
            $dispatcher->dispatch(self::sessionFinished());

            // Assert — constructor path is written; CLI path is untouched.
            Assert::true(\file_exists($constructorPath));
            Assert::false(\file_exists($cliPath));
        } finally {
            self::cleanup($constructorPath);
            self::cleanup($cliPath);
        }
    }

    public function cliFlagActivatesInertInstance(): void
    {
        // Arrange — no constructor path (inert default); CLI flag should activate it.
        $cliPath = self::tmpPath();
        try {
            $dispatcher = self::wirePlugin(new JUnitPlugin(), cliPath: $cliPath);

            // Act
            $dispatcher->dispatch(new SessionStarting());
            $dispatcher->dispatch(self::sessionFinished());

            // Assert
            Assert::true(\file_exists($cliPath));
        } finally {
            self::cleanup($cliPath);
        }
    }

    public function inertWithoutPathFromAnySource(): void
    {
        // Arrange — no constructor path, no CLI path → plugin must not register listeners.
        $dispatcher = self::wirePlugin(new JUnitPlugin());

        // Act — drive a full session; nothing should blow up and no file should appear.
        $dispatcher->dispatch(new SessionStarting());
        $dispatcher->dispatch(self::sessionFinished());

        // Assert — listener registry is empty for our events; absence of crash already implies no-op.
        Assert::same(\iterator_to_array($dispatcher->getListenersForEvent(new SessionStarting()), false), []);
        Assert::same(\iterator_to_array($dispatcher->getListenersForEvent(self::sessionFinished()), false), []);
    }

    public function sessionStartingResetsBetweenRuns(): void
    {
        // Arrange — emit two sessions through the same plugin instance; the
        // second run must not contain residue from the first.
        $path = self::tmpPath();
        try {
            $plugin = new JUnitPlugin($path);
            $dispatcher = self::wirePlugin($plugin);

            $suiteInfo = self::makeSuiteInfo('Run1');
            $caseInfo = self::makeCaseInfo();
            $testInfo = self::makeTestInfo('passingTest');

            // Run 1
            $dispatcher->dispatch(new SessionStarting());
            $dispatcher->dispatch(new TestSuiteStarting($suiteInfo));
            $dispatcher->dispatch(new TestCaseStarting($caseInfo));
            $dispatcher->dispatch(new TestPipelineFinished(
                $testInfo,
                new TestResult($testInfo, Status::Passed),
            ));
            $dispatcher->dispatch(new TestCaseFinished($caseInfo, new CaseResult([], Status::Passed)));
            $dispatcher->dispatch(new TestSuiteFinished($suiteInfo, new SuiteResult([], Status::Passed)));
            $dispatcher->dispatch(self::sessionFinished());

            // Run 2 — fresh, but only one suite "Run2" with no cases.
            $suiteInfo2 = self::makeSuiteInfo('Run2');
            $dispatcher->dispatch(new SessionStarting());
            $dispatcher->dispatch(new TestSuiteStarting($suiteInfo2));
            $dispatcher->dispatch(new TestSuiteFinished($suiteInfo2, new SuiteResult([], Status::Passed)));
            $dispatcher->dispatch(self::sessionFinished());

            // Act
            $xml = \simplexml_load_file($path);

            // Assert — only Run2 is present, totals are zero.
            Assert::notSame($xml, false);
            Assert::same((string) $xml['tests'], '0');
            Assert::same((string) $xml->testsuite['name'], 'Run2');
            Assert::count($xml->testsuite, 1);
        } finally {
            self::cleanup($path);
        }
    }

    public function theFileIsAnnouncedAsAPromiseAndThenAsAFact(): void
    {
        $path = self::tmpPath();
        try {
            $dispatcher = self::wirePlugin(new JUnitPlugin($path));

            /** @var list<array{event: ReportFileGenerating|ReportFileGenerated, existed: bool}> $seen */
            $seen = [];
            $record = static function (ReportFileGenerating|ReportFileGenerated $event) use (&$seen): void {
                $seen[] = ['event' => $event, 'existed' => \is_file((string) $event->info->path)];
            };
            $dispatcher->addListener(ReportFileGenerating::class, $record);
            $dispatcher->addListener(ReportFileGenerated::class, $record);

            $dispatcher->dispatch(new SessionStarting());
            $dispatcher->dispatch(self::sessionFinished());

            // The early one lands while the run tree is still open; the late one means the XML can be read.
            Assert::count($seen, 2);
            Assert::true($seen[0]['event'] instanceof ReportFileGenerating);
            Assert::false($seen[0]['existed']);
            Assert::true($seen[1]['event'] instanceof ReportFileGenerated);
            Assert::true($seen[1]['existed']);

            Assert::same($seen[0]['event']->info->format, 'junit');
            Assert::same((string) $seen[0]['event']->info->path, (string) $seen[1]['event']->info->path);
        } finally {
            self::cleanup($path);
        }
    }

    /**
     * Wires a freshly built plugin into a real {@see EventDispatcher}.
     */
    private static function wirePlugin(JUnitPlugin $plugin, ?string $cliPath = null): EventDispatcher
    {
        $dispatcher = new EventDispatcher();
        $container = new ObjectContainer();
        $container->set($dispatcher, EventListenerCollector::class);
        $container->set($dispatcher, EventDispatcherInterface::class);

        $input = new JUnitInput();
        $input->outputPath = $cliPath;
        $container->set($input, JUnitInput::class);

        $plugin->configure($container);

        return $dispatcher;
    }

    private static function sessionFinished(): SessionFinished
    {
        return new SessionFinished(new RunResult([], Status::Passed, 0.0));
    }

    /**
     * @param non-empty-string $name
     */
    private static function makeSuiteInfo(string $name): SuiteInfo
    {
        return new SuiteInfo(name: $name, testCases: new CaseDefinitions());
    }

    private static function makeCaseInfo(): CaseInfo
    {
        return new CaseInfo(
            suiteIdentity: new SuiteIdentity('Output/Unit'),
            definition: new CaseDefinition(
                name: SampleTestClass::class,
                type: 'test',
                file: Path::create(__FILE__),
                reflection: new \ReflectionClass(SampleTestClass::class),
            ),
        );
    }

    /**
     * @param non-empty-string $method
     */
    private static function makeTestInfo(string $method): TestInfo
    {
        return new TestInfo(
            name: $method,
            caseInfo: self::makeCaseInfo(),
            testDefinition: new TestDefinition(new \ReflectionMethod(SampleTestClass::class, $method)),
        );
    }

    private static function makeFreeFunctionCaseInfo(): CaseInfo
    {
        // No class reflection — emulates how Testo builds a case for a file
        // containing free-function tests (see CaseDefinitions::define).
        return new CaseInfo(
            suiteIdentity: new SuiteIdentity('Output/Unit'),
            definition: new CaseDefinition(
                name: 'free_function_helper.php',
                type: 'test',
                file: Path::create(__FILE__),
                reflection: null,
            ),
        );
    }

    /**
     * @param non-empty-string $functionFqn
     */
    private static function makeFreeFunctionTestInfo(CaseInfo $caseInfo, string $functionFqn): TestInfo
    {
        $reflection = new \ReflectionFunction($functionFqn);

        # Discovery keys a test by its short name and the runner passes that key straight through, so a
        # name that disagrees with the reflection is a shape the runtime never produces.
        return new TestInfo(
            name: $reflection->getShortName(),
            caseInfo: $caseInfo,
            testDefinition: new TestDefinition($reflection),
        );
    }

    /**
     * Git-ignored scratch root inside this module's tests. Avoids
     * `sys_get_temp_dir()`, whose value can be a non-Windows path under some
     * agent runners and breaks `mkdir()`.
     */
    private static function tmpDir(): string
    {
        return \dirname(__DIR__, 3) . '/runtime';
    }

    /**
     * @return non-empty-string
     */
    private static function tmpPath(): string
    {
        return self::tmpDir() . '/testo_junit_plugin_' . \uniqid() . '.xml';
    }

    private static function cleanup(string $path): void
    {
        \is_file($path) and \unlink($path);
        $dir = \dirname($path);
        // Don't blow up on nested temp dirs that other tests may share.
        if (\is_dir($dir) && $dir !== self::tmpDir()) {
            // Best-effort: only remove if empty.
            @\rmdir($dir);
        }
    }
}
