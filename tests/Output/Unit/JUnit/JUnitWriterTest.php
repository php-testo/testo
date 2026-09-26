<?php

declare(strict_types=1);

namespace Tests\Output\Unit\JUnit;

use Internal\Path;
use Testo\Assert;
use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\Line;
use Testo\Bench\Dto\Snap;
use Testo\Bench\Dto\ValueRel;
use Testo\Codecov\Covers;
use Testo\Common\Messenger;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Exception\SkipTest;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Core\Log\MessageLog;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Output\JUnit\Internal\JUnitWriter;
use Testo\Test;
use Tests\Output\Stub\JUnit\ConcreteSampleTest;
use Tests\Output\Stub\JUnit\SampleTestClass;

#[Test]
final class JUnitWriterTest
{
    public function emptyRunProducesValidEmptyDocument(): void
    {
        // Arrange
        $writer = new JUnitWriter();

        // Act
        $xml = self::loadXml($writer->generate('Testo'));

        // Assert
        Assert::same((string) $xml['name'], 'Testo');
        Assert::same((string) $xml['tests'], '0');
        Assert::same((string) $xml['failures'], '0');
        Assert::same((string) $xml['errors'], '0');
        Assert::same((string) $xml['skipped'], '0');
        Assert::count($xml->testsuite, 0);
    }

    public function singleSuiteWithPassingTest(): void
    {
        // Arrange
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed, durationMs: 12));
        $writer->finishSuite();

        // Act
        $xml = self::loadXml($writer->generate('Testo'));

        // Assert: rolled-up totals
        Assert::same((string) $xml['tests'], '1');
        Assert::same((string) $xml['failures'], '0');
        Assert::same((string) $xml['errors'], '0');
        Assert::same((string) $xml['skipped'], '0');

        // Suite
        $suite = $xml->testsuite;
        Assert::same((string) $suite['name'], 'MySuite');
        Assert::same((string) $suite['tests'], '1');
        Assert::same((string) $suite['failures'], '0');

        // Test case
        $case = $suite->testcase;
        Assert::same((string) $case['name'], 'passingTest');
        Assert::same((string) $case['classname'], SampleTestClass::class);
        Assert::same((string) $case['time'], '0.012000');
        // No <failure>, <error>, <skipped>
        Assert::count($case->failure, 0);
        Assert::count($case->error, 0);
        Assert::count($case->skipped, 0);
    }

    public function suiteCarriesOptionalFileAttribute(): void
    {
        // Arrange — `file` on `<testsuite>` is consumed by Infection's
        // `JUnitTestFileDataProvider` to locate test source files.
        $writer = new JUnitWriter();
        $writer->startSuite(SampleTestClass::class, '/abs/path/SampleTestClass.php');
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed));
        $writer->finishSuite();

        // Act
        $xml = self::loadXml($writer->generate('Testo'));

        // Assert
        Assert::same((string) $xml->testsuite['file'], '/abs/path/SampleTestClass.php');
    }

    public function failingTestRendersFailureElement(): void
    {
        // Arrange
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $exception = new \RuntimeException('Expected 5, got 4');
        $writer->addTestResult(self::makeResult(
            'failingTest',
            Status::Failed,
            durationMs: 5,
            failure: $exception,
        ));
        $writer->finishSuite();

        // Act
        $xml = self::loadXml($writer->generate('Testo'));

        // Assert
        Assert::same((string) $xml['failures'], '1');
        Assert::same((string) $xml['errors'], '0');

        $failure = $xml->testsuite->testcase->failure;
        Assert::count($failure, 1);
        Assert::same((string) $failure['type'], \RuntimeException::class);
        Assert::same((string) $failure['message'], 'Expected 5, got 4');
        // CDATA payload should mention the file and "Stack trace:" header.
        Assert::true(\str_contains((string) $failure, 'Stack trace:'));
    }

    public function erroredTestRendersErrorElement(): void
    {
        // Arrange
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult(
            'failingTest',
            Status::Error,
            failure: new \LogicException('Boom'),
        ));
        $writer->finishSuite();

        // Act
        $xml = self::loadXml($writer->generate('Testo'));

        // Assert
        Assert::same((string) $xml['errors'], '1');
        Assert::same((string) $xml['failures'], '0');
        $error = $xml->testsuite->testcase->error;
        Assert::count($error, 1);
        Assert::same((string) $error['type'], \LogicException::class);
        Assert::same((string) $error['message'], 'Boom');
    }

    public function skippedTestRendersSkippedElement(): void
    {
        // Arrange
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult('passingTest', Status::Skipped));
        $writer->finishSuite();

        // Act
        $xml = self::loadXml($writer->generate('Testo'));

        // Assert
        Assert::same((string) $xml['skipped'], '1');
        Assert::count($xml->testsuite->testcase->skipped, 1);
    }

    /**
     * The failure message is the single source of truth for the skip reason: every producer
     * (a runtime throw, a declarative `#[Skip]`) delivers it the same way, and the writer
     * renders it as the `message` of `<skipped>`.
     */
    #[Covers(JUnitWriter::class)]
    public function skippedTestCarriesTheReasonFromTheFailureMessage(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult(
            'passingTest',
            Status::Skipped,
            failure: new SkipTest('sqlite extension is missing'),
        ));
        $writer->finishSuite();

        $xml = self::loadXml($writer->generate('Testo'));

        $skipped = $xml->testsuite->testcase->skipped;
        Assert::count($skipped, 1);
        Assert::same((string) $skipped['message'], 'sqlite extension is missing');
    }

    /**
     * No reason — no `message`: an empty attribute would read as an empty reason.
     */
    #[Covers(JUnitWriter::class)]
    public function skippedTestWithoutAReasonOmitsTheMessage(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult(
            'passingTest',
            Status::Skipped,
            failure: new SkipTest(),
        ));
        $writer->finishSuite();

        $xml = self::loadXml($writer->generate('Testo'));

        $skipped = $xml->testsuite->testcase->skipped;
        Assert::count($skipped, 1);
        Assert::null($skipped['message']);
    }

    public function cancelledTestCountsAsSkipped(): void
    {
        // Arrange
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult('passingTest', Status::Cancelled));
        $writer->finishSuite();

        // Act
        $xml = self::loadXml($writer->generate('Testo'));

        // Assert
        Assert::same((string) $xml['skipped'], '1');
        Assert::count($xml->testsuite->testcase->skipped, 1);
    }

    #[Covers(JUnitWriter::class)]
    public function benchmarkTestCarriesItsMeasurementsAsProperties(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeBenchResult('shiftVsPush'));
        $writer->finishSuite();

        $xml = self::loadXml($writer->generate('Testo'));

        $properties = $xml->testsuite->testcase->properties;
        Assert::count($properties, 1);

        // `<property name value>` pairs — the shape PHPUnit emits, keyed to survive as data.
        $byName = [];
        foreach ($properties->property as $property) {
            $byName[(string) $property['name']] = (string) $property['value'];
        }
        Assert::same($byName['bench.iterations'], '1');
        Assert::same($byName['bench.shift.meanUs'], '5.1');
        Assert::same($byName['bench.shift.calls'], '20');
    }

    #[Covers(JUnitWriter::class)]
    public function anOrdinaryTestWritesNoPropertiesElement(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed));
        $writer->finishSuite();

        $xml = self::loadXml($writer->generate('Testo'));

        Assert::count($xml->testsuite->testcase->properties, 0);
    }

    #[Covers(JUnitWriter::class)]
    public function aDataSetBenchmarkStillCarriesItsMeasurements(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(
            self::makeBenchResult('shiftVsPush'),
            overrideName: 'shiftVsPush#0',
            providerIndex: 0,
            datasetIndex: 1,
        );
        $writer->finishSuite();

        $xml = self::loadXml($writer->generate('Testo'));
        $case = $xml->testsuite->testcase;

        // The measurements ride on the data-set entry itself, under its own name, not on an umbrella case.
        Assert::same((string) $case['name'], 'shiftVsPush#0');
        Assert::count($case->properties, 1);
    }

    public function abortedTestCountsAsError(): void
    {
        // Arrange
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult(
            'failingTest',
            Status::Aborted,
            failure: new \RuntimeException('aborted by interceptor'),
        ));
        $writer->finishSuite();

        // Act
        $xml = self::loadXml($writer->generate('Testo'));

        // Assert
        Assert::same((string) $xml['errors'], '1');
        $error = $xml->testsuite->testcase->error;
        Assert::same((string) $error['type'], 'Aborted');
        Assert::same((string) $error['message'], 'aborted by interceptor');
    }

    public function riskyTestCountsAsError(): void
    {
        // Arrange — Risky is mapped to <error type="Risky"> for broadest CI compatibility.
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult('passingTest', Status::Risky));
        $writer->finishSuite();

        // Act
        $xml = self::loadXml($writer->generate('Testo'));

        // Assert
        Assert::same((string) $xml['errors'], '1');
        $error = $xml->testsuite->testcase->error;
        Assert::same((string) $error['type'], 'Risky');
    }

    public function flakyTestCountsAsPass(): void
    {
        // Arrange
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult('passingTest', Status::Flaky));
        $writer->finishSuite();

        // Act
        $xml = self::loadXml($writer->generate('Testo'));

        // Assert
        Assert::same((string) $xml['tests'], '1');
        Assert::same((string) $xml['failures'], '0');
        Assert::same((string) $xml['errors'], '0');
        $case = $xml->testsuite->testcase;
        Assert::count($case->failure, 0);
        Assert::count($case->error, 0);
        Assert::count($case->skipped, 0);
    }

    public function mixedRunRollsCountersUp(): void
    {
        // Arrange
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed, durationMs: 10));
        $writer->addTestResult(self::makeResult(
            'failingTest',
            Status::Failed,
            durationMs: 20,
            failure: new \RuntimeException('boom'),
        ));
        $writer->addTestResult(self::makeResult('passingTest', Status::Skipped));
        $writer->addTestResult(self::makeResult(
            'failingTest',
            Status::Error,
            failure: new \LogicException('crash'),
        ));
        $writer->finishSuite();

        // Act
        $xml = self::loadXml($writer->generate('Testo'));

        // Assert: top-level totals
        Assert::same((string) $xml['tests'], '4');
        Assert::same((string) $xml['failures'], '1');
        Assert::same((string) $xml['errors'], '1');
        Assert::same((string) $xml['skipped'], '1');

        // Suite mirrors
        Assert::same((string) $xml->testsuite['tests'], '4');
        Assert::same((string) $xml->testsuite['failures'], '1');
        Assert::same((string) $xml->testsuite['errors'], '1');
        Assert::same((string) $xml->testsuite['skipped'], '1');
    }

    public function dataProviderProducesOneCasePerDataset(): void
    {
        // Arrange
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed), 'passingTest [zero]');
        $writer->addTestResult(self::makeResult(
            'passingTest',
            Status::Failed,
            failure: new \RuntimeException('nope'),
        ), 'passingTest [one]');
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed), 'passingTest [two]');
        $writer->finishSuite();

        // Act
        $xml = self::loadXml($writer->generate('Testo'));

        // Assert
        Assert::same((string) $xml['tests'], '3');
        Assert::same((string) $xml['failures'], '1');
        $cases = $xml->testsuite->testcase;
        Assert::count($cases, 3);
        Assert::same((string) $cases[0]['name'], 'passingTest [zero]');
        Assert::same((string) $cases[1]['name'], 'passingTest [one]');
        Assert::same((string) $cases[2]['name'], 'passingTest [two]');
    }

    public function nestedSuiteRepresentsTheClassLayer(): void
    {
        // Arrange — outer suite (TestSuite) wraps inner suite (TestCase/class layer).
        $writer = new JUnitWriter();
        $writer->startSuite('OuterSuite');
        $writer->startSuite('SampleTestClass [test]');
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed, durationMs: 5));
        $writer->addTestResult(self::makeResult(
            'failingTest',
            Status::Failed,
            durationMs: 10,
            failure: new \RuntimeException('boom'),
        ));
        $writer->finishSuite(); // close class layer
        $writer->finishSuite(); // close outer

        // Act
        $xml = self::loadXml($writer->generate('Testo'));

        // Assert: nested structure
        $outer = $xml->testsuite;
        Assert::same((string) $outer['name'], 'OuterSuite');
        // Counters bubble up across nesting
        Assert::same((string) $outer['tests'], '2');
        Assert::same((string) $outer['failures'], '1');

        $inner = $outer->testsuite;
        Assert::same((string) $inner['name'], 'SampleTestClass [test]');
        Assert::same((string) $inner['tests'], '2');
        Assert::same((string) $inner['failures'], '1');
        Assert::count($inner->testcase, 2);
    }

    public function freeFunctionUsesFqnAsClassname(): void
    {
        // Arrange — function declared in `Tests\Output\Stub\JUnit` namespace.
        require_once __DIR__ . '/../../Stub/JUnit/free_function_helper.php';
        $reflection = new \ReflectionFunction('Tests\\Output\\Stub\\JUnit\\junitFreeFunction');

        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResultWithReflection($reflection, Status::Passed));
        $writer->finishSuite();

        // Act
        $xml = self::loadXml($writer->generate('Testo'));

        // Assert: function FQN serves as classname for free-function tests so
        // it matches the per-function <testsuite name="..."> opened by the plugin
        // and Infection's `<covered by="...">` mapping.
        Assert::same(
            (string) $xml->testsuite->testcase['classname'],
            'Tests\\Output\\Stub\\JUnit\\junitFreeFunction',
        );
    }

    public function inheritedTestMethodUsesConcreteCaseClassAsClassname(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite(ConcreteSampleTest::class, '/abs/ConcreteSampleTest.php');
        $writer->addTestResult(self::makeInheritedResult(Status::Passed));
        $writer->finishSuite();

        $xml = self::loadXml($writer->generate('Testo'));

        // The <testsuite> is named after the concrete class; the <testcase>
        // classname must match it so Infection's `//testsuite[@name="FQN"]`
        // lookup — keyed on the coverage `<covered by>` class — resolves.
        // getDeclaringClass() would name the abstract base and break the mapping.
        Assert::same((string) $xml->testsuite->testcase['classname'], ConcreteSampleTest::class);
    }

    #[Covers(JUnitWriter::class)]
    public function testcaseCarriesTheAssertionCountOfItsSummary(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed, assertions: 3));
        $writer->finishSuite();

        $xml = self::loadXml($writer->generate('Testo'));

        Assert::same((string) $xml->testsuite->testcase['assertions'], '3');
    }

    /**
     * Without the Assert plugin nothing records the metric: the attribute stays and reads `0`,
     * the number the terminal prints for the same run.
     */
    #[Covers(JUnitWriter::class)]
    public function testcaseWithoutRecordedAssertionsReportsZero(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed));
        $writer->finishSuite();

        $xml = self::loadXml($writer->generate('Testo'));

        Assert::same((string) $xml['assertions'], '0');
        Assert::same((string) $xml->testsuite['assertions'], '0');
        Assert::same((string) $xml->testsuite->testcase['assertions'], '0');
    }

    #[Covers(JUnitWriter::class)]
    public function assertionsRollUpThroughNestedSuitesToTheRoot(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('OuterSuite');
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed, assertions: 1));
        $writer->startSuite('InnerSuite');
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed, assertions: 2));
        $writer->addTestResult(self::makeResult(
            'failingTest',
            Status::Failed,
            failure: new \RuntimeException('boom'),
            assertions: 4,
        ));
        $writer->finishSuite();
        $writer->finishSuite();
        $writer->startSuite('SiblingSuite');
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed, assertions: 8));
        $writer->finishSuite();

        $xml = self::loadXml($writer->generate('Testo'));

        Assert::same((string) $xml['assertions'], '15');
        Assert::same((string) $xml->testsuite[0]['assertions'], '7');
        Assert::same((string) $xml->testsuite[0]->testsuite['assertions'], '6');
        Assert::same((string) $xml->testsuite[1]['assertions'], '8');
    }

    public function writeCreatesParentDirectory(): void
    {
        // Arrange
        $tmpDir = \dirname(__DIR__, 3) . '/runtime/testo_junit_' . \uniqid();
        $path = $tmpDir . '/nested/junit.xml';
        try {
            $writer = new JUnitWriter();
            $writer->startSuite('MySuite');
            $writer->addTestResult(self::makeResult('passingTest', Status::Passed));
            $writer->finishSuite();

            // Act
            $writer->write(\Internal\Path::create($path), 'Testo');

            // Assert
            Assert::true(\file_exists($path));
            $loaded = \simplexml_load_file($path);
            Assert::notSame($loaded, false);
            Assert::same((string) $loaded['name'], 'Testo');
        } finally {
            self::cleanup($tmpDir);
        }
    }

    public function writeAutoClosesOpenSuites(): void
    {
        // Arrange — defensive: SessionFinished shouldn't crash on dangling suites.
        $writer = new JUnitWriter();
        $writer->startSuite('Outer');
        $writer->startSuite('Inner');
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed));

        // Act — generate without explicit finishSuite() calls.
        $xml = self::loadXml($writer->generate('Testo'));

        // Assert
        Assert::same((string) $xml->testsuite['name'], 'Outer');
        Assert::same((string) $xml->testsuite->testsuite['name'], 'Inner');
    }

    public function failureMessageWithSpecialCharsIsEncodedSafely(): void
    {
        // Arrange — embed a literal `]]>` in the message to verify the CDATA-escape path.
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult(
            'failingTest',
            Status::Failed,
            failure: new \RuntimeException('contains ]]> sequence'),
        ));
        $writer->finishSuite();
        $xml = $writer->generate('Testo');

        // Act — XML must remain well-formed.
        $loaded = \simplexml_load_string($xml);

        // Assert
        Assert::notSame($loaded, false);
        Assert::same((string) $loaded->testsuite->testcase->failure['message'], 'contains ]]> sequence');
    }

    #[Covers(JUnitWriter::class)]
    public function controlBytesAndBrokenUtf8KeepTheDocumentWellFormed(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult(
            'failingTest',
            Status::Failed,
            failure: new \RuntimeException("\e[31mred\e[0m \xFF end\0"),
        ));
        $writer->finishSuite();

        $xml = self::loadXml($writer->generate('Testo'));

        $failure = $xml->testsuite->testcase->failure;
        Assert::same((string) $failure['message'], "[31mred[0m \u{FFFD} end");
        Assert::string((string) $failure)->contains('Stack trace:');
    }

    #[Covers(JUnitWriter::class)]
    public function nativeOutputGoesToSystemOutAndSuppressedErrorsToSystemErr(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed, messages: new MessageLog([
            new Message(0.1, Messenger::CHANNEL_STDOUT, Level::Info, "first line\n"),
            new Message(0.2, Messenger::CHANNEL_STDERR, Level::Error, "listener failed\n"),
            new Message(0.3, 'retry', Level::Warning, "Attempt 1 failed.\n"),
            new Message(0.4, Messenger::CHANNEL_STDOUT, Level::Info, 'tail ]]> <b>'),
        ])));
        $writer->finishSuite();

        $case = self::loadXml($writer->generate('Testo'))->testsuite->testcase;

        Assert::same((string) $case->{'system-out'}, "first line\ntail ]]> <b>");
        Assert::same((string) $case->{'system-err'}, "listener failed\n");
    }

    #[Covers(JUnitWriter::class)]
    public function aTestWithoutOutputWritesNoOutputElements(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed));
        $writer->finishSuite();

        $case = self::loadXml($writer->generate('Testo'))->testsuite->testcase;

        Assert::count($case->{'system-out'}, 0);
        Assert::count($case->{'system-err'}, 0);
    }

    #[Covers(JUnitWriter::class)]
    public function suiteStartTimeIsWrittenAsTimestampWithoutOffset(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('Unit', startedAt: new \DateTimeImmutable('2026-09-26 14:03:07.250', new \DateTimeZone('+04:00')));
        $writer->startSuite(SampleTestClass::class);
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed));
        $writer->finishSuite();
        $writer->finishSuite();

        $suite = self::loadXml($writer->generate('Testo'))->testsuite;

        Assert::same((string) $suite['timestamp'], '2026-09-26T14:03:07');
        Assert::null($suite->testsuite['timestamp']);
    }

    #[Covers(JUnitWriter::class)]
    public function hostnameGoesOnTopLevelSuitesOnly(): void
    {
        $writer = new JUnitWriter('ci-runner-7');
        $writer->startSuite('Unit');
        $writer->startSuite(SampleTestClass::class);
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed));
        $writer->finishSuite();
        $writer->finishSuite();
        $writer->startSuite('Feature');
        $writer->finishSuite();

        $xml = self::loadXml($writer->generate('Testo'));

        Assert::null($xml['hostname']);
        Assert::same((string) $xml->testsuite[0]['hostname'], 'ci-runner-7');
        Assert::same((string) $xml->testsuite[1]['hostname'], 'ci-runner-7');
        Assert::null($xml->testsuite[0]->testsuite['hostname']);
    }

    #[Covers(JUnitWriter::class)]
    public function noHostnameLeavesTheAttributeOut(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('Unit');
        $writer->finishSuite();

        Assert::null(self::loadXml($writer->generate('Testo'))->testsuite['hostname']);
    }

    #[Covers(JUnitWriter::class)]
    public function attemptsDiscardedBeforeAPassAreWrittenAsFlakyElements(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(
            self::makeResult('passingTest', Status::Flaky),
            discardedAttempts: [
                self::makeResult(
                    'passingTest',
                    Status::Failed,
                    failure: new \RuntimeException('first try'),
                    messages: new MessageLog([new Message(0.1, Messenger::CHANNEL_STDOUT, Level::Info, 'attempt 1')]),
                ),
                self::makeResult('passingTest', Status::Error, failure: new \LogicException('second try')),
            ],
        );
        $writer->finishSuite();

        $xml = self::loadXml($writer->generate('Testo'));

        Assert::same((string) $xml['failures'], '0');
        Assert::same((string) $xml['errors'], '0');
        $case = $xml->testsuite->testcase;
        Assert::count($case->failure, 0);
        Assert::count($case->flakyFailure, 1);
        Assert::same((string) $case->flakyFailure['type'], \RuntimeException::class);
        Assert::same((string) $case->flakyFailure['message'], 'first try');
        Assert::string((string) $case->flakyFailure->stackTrace)->contains('Stack trace:');
        Assert::same((string) $case->flakyFailure->{'system-out'}, 'attempt 1');
        Assert::count($case->flakyError, 1);
        Assert::same((string) $case->flakyError['type'], \LogicException::class);
        Assert::count($case->flakyError->{'system-out'}, 0);
    }

    #[Covers(JUnitWriter::class)]
    public function attemptsDiscardedBeforeAFinalFailureAreWrittenAsRerunElements(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(
            self::makeResult('failingTest', Status::Failed, failure: new \RuntimeException('third try')),
            discardedAttempts: [
                self::makeResult('failingTest', Status::Failed),
                self::makeResult('failingTest', Status::Error, failure: new \LogicException('second try')),
            ],
        );
        $writer->finishSuite();

        $case = self::loadXml($writer->generate('Testo'))->testsuite->testcase;

        Assert::same((string) $case->failure['message'], 'third try');
        Assert::count($case->rerunFailure, 1);
        Assert::same((string) $case->rerunFailure['type'], 'Failure');
        Assert::null($case->rerunFailure['message']);
        Assert::count($case->rerunFailure->stackTrace, 1);
        Assert::count($case->rerunError, 1);
        Assert::same((string) $case->rerunError['message'], 'second try');
        Assert::count($case->flakyFailure, 0);
    }

    #[Covers(JUnitWriter::class)]
    public function everyTestcaseCarriesTheExactTestoStatus(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        foreach ([Status::Passed, Status::Flaky, Status::Risky, Status::Aborted, Status::Cancelled] as $status) {
            $writer->addTestResult(self::makeResult('passingTest', $status));
        }
        $writer->finishSuite();

        $dom = new \DOMDocument();
        Assert::true($dom->loadXML($writer->generate('Testo')));
        $statuses = [];
        foreach ($dom->getElementsByTagName('testcase') as $case) {
            $statuses[] = $case->getAttributeNS(JUnitWriter::TESTO_NS, 'status');
        }

        Assert::same($statuses, ['passed', 'flaky', 'risky', 'aborted', 'cancelled']);
    }

    #[Covers(JUnitWriter::class)]
    public function failureTraceStopsAtTheTestFunction(): void
    {
        $failure = self::thrownBy('throwingTest');
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult('throwingTest', Status::Failed, failure: $failure));
        $writer->finishSuite();

        $details = (string) self::loadXml($writer->generate('Testo'))->testsuite->testcase->failure;

        Assert::string($details)->startsWith(
            "LogicException: thrown from a helper\nFile: {$failure->getFile()}:{$failure->getLine()}\n\nStack trace:\n",
        );
        $frames = self::framesOf($details);
        Assert::count($frames, 2);
        Assert::string($frames[0])->endsWith(SampleTestClass::class . '->throwFromHelper()');
        Assert::string($frames[1])->startsWith('#1 ')->endsWith(SampleTestClass::class . '->throwingTest()');
    }

    #[Covers(JUnitWriter::class)]
    public function previousExceptionsAreCutAtTheTestFunctionToo(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult('wrappingTest', Status::Error, failure: self::thrownBy('wrappingTest')));
        $writer->finishSuite();

        $details = (string) self::loadXml($writer->generate('Testo'))->testsuite->testcase->error;

        $parts = \explode("\n\nCaused by:\n", $details);
        Assert::count($parts, 2);
        Assert::string($parts[0])->startsWith('RuntimeException: wrapped');
        Assert::count(self::framesOf($parts[0]), 1);
        Assert::string($parts[1])->startsWith('LogicException: thrown from a helper');
        $frames = self::framesOf($parts[1]);
        Assert::count($frames, 2);
        Assert::string($frames[1])->endsWith(SampleTestClass::class . '->wrappingTest()');
    }

    #[Covers(JUnitWriter::class)]
    public function aTraceWithoutTheTestFunctionKeepsTheThrowSite(): void
    {
        $line = __LINE__ + 1;
        $failure = new \RuntimeException('raised around the test');
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(self::makeResult('throwingTest', Status::Aborted, failure: $failure));
        $writer->finishSuite();

        $details = (string) self::loadXml($writer->generate('Testo'))->testsuite->testcase->error;

        Assert::string($details)->contains('File: ' . __FILE__ . ':' . $line);
        Assert::string(self::framesOf($details)[0])->endsWith(self::class . '->' . __FUNCTION__ . '()');
    }

    #[Covers(JUnitWriter::class)]
    public function discardedAttemptTracesStopAtTheTestFunction(): void
    {
        $writer = new JUnitWriter();
        $writer->startSuite('MySuite');
        $writer->addTestResult(
            self::makeResult('throwingTest', Status::Flaky),
            discardedAttempts: [self::makeResult('throwingTest', Status::Failed, failure: self::thrownBy('throwingTest'))],
        );
        $writer->addTestResult(
            self::makeResult('throwingTest', Status::Failed, failure: self::thrownBy('throwingTest')),
            discardedAttempts: [self::makeResult('throwingTest', Status::Error, failure: self::thrownBy('throwingTest'))],
        );
        $writer->finishSuite();

        $cases = self::loadXml($writer->generate('Testo'))->testsuite->testcase;

        foreach ([$cases[0]->flakyFailure->stackTrace, $cases[1]->rerunError->stackTrace] as $stackTrace) {
            $frames = self::framesOf((string) $stackTrace);
            Assert::count($frames, 2);
            Assert::string($frames[1])->endsWith(SampleTestClass::class . '->throwingTest()');
        }
    }

    #[Covers(JUnitWriter::class)]
    public function reportValidatesAgainstTheBundledSchema(): void
    {
        $writer = new JUnitWriter(hostname: 'ci-runner');
        $writer->startSuite('Output/Unit', startedAt: new \DateTimeImmutable('2026-09-26 10:00:00'));
        $writer->startSuite(SampleTestClass::class, __FILE__);
        foreach (Status::cases() as $status) {
            $writer->addTestResult(self::makeResult(
                'passingTest',
                $status,
                durationMs: 3,
                failure: $status === Status::Passed ? null : new \RuntimeException("{$status->name} \e[31m\xff"),
                assertions: 2,
                messages: new MessageLog([new Message(0.1, Messenger::CHANNEL_STDOUT, Level::Info, 'out')]),
            ));
        }
        $writer->addTestResult(
            self::makeResult('passingTest', Status::Flaky),
            'passingTest [first]',
            providerIndex: 0,
            datasetIndex: 1,
            datasetKey: 'first',
            discardedAttempts: [self::makeResult('passingTest', Status::Failed, failure: new \LogicException('retry'))],
        );
        $writer->addTestResult(self::makeBenchResult('passingTest'));
        $writer->finishSuite();
        $writer->finishSuite();

        $dom = new \DOMDocument();
        Assert::true($dom->loadXML($writer->generate('Testo')));

        $previous = \libxml_use_internal_errors(true);
        try {
            # Located from the writer, not from this file: the test also runs from a converted copy elsewhere.
            $schema = \dirname((string) (new \ReflectionClass(JUnitWriter::class))->getFileName(), 2) . '/schema/junit.xsd';
            $valid = $dom->schemaValidate($schema);
            $errors = \array_map(static fn(\LibXMLError $error): string => \trim($error->message), \libxml_get_errors());
        } finally {
            \libxml_clear_errors();
            \libxml_use_internal_errors($previous);
        }

        Assert::same($errors, []);
        Assert::true($valid);
    }

    public function resetClearsAccumulatedState(): void
    {
        // Arrange
        $writer = new JUnitWriter();
        $writer->startSuite('Discarded');
        $writer->addTestResult(self::makeResult('passingTest', Status::Passed));
        $writer->finishSuite();

        // Act
        $writer->reset();
        $xml = self::loadXml($writer->generate('Testo'));

        // Assert
        Assert::same((string) $xml['tests'], '0');
        Assert::count($xml->testsuite, 0);
    }

    /**
     * @param non-empty-string $method
     */
    private static function makeResult(
        string $method,
        Status $status,
        int $durationMs = 0,
        ?\Throwable $failure = null,
        ?int $assertions = null,
        MessageLog $messages = new MessageLog(),
    ): TestResult {
        $reflection = new \ReflectionMethod(SampleTestClass::class, $method);

        $info = new TestInfo(
            name: $method,
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Output/Unit'),
                definition: new CaseDefinition(
                    name: SampleTestClass::class,
                    type: 'test',
                    file: Path::create(__FILE__),
                    reflection: new \ReflectionClass(SampleTestClass::class),
                ),
            ),
            testDefinition: new TestDefinition($reflection),
        );

        return new TestResult(
            info: $info,
            status: $status,
            failure: $failure,
            attributes: ['duration' => $durationMs],
            messages: $messages,
            summary: $assertions === null ? new Summary() : new Summary(metrics: ['assertions' => $assertions]),
        );
    }

    private static function makeBenchResult(string $method): TestResult
    {
        $info = new TestInfo(
            name: $method,
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Output/Unit'),
                definition: new CaseDefinition(
                    name: SampleTestClass::class,
                    type: 'bench',
                    file: Path::create(__FILE__),
                    reflection: new \ReflectionClass(SampleTestClass::class),
                ),
            ),
            testDefinition: new TestDefinition(new \ReflectionMethod(SampleTestClass::class, 'passingTest')),
        );

        $bench = new BenchResult(
            cases: [new CaseSet('shift', [new Snap(calls: 20, memory: 0, time: 5.1)])],
            results: [],
            lines: [
                new Line(
                    place: 1,
                    name: 'shift',
                    avg: new ValueRel(5.1, 0.0),
                    med: new ValueRel(5.1, 0.0),
                    rstdev: 2.0,
                    favg: new ValueRel(5.1, 0.0),
                    frstdev: 2.0,
                    rejected: 0,
                    reports: [],
                ),
            ],
        );

        return new TestResult(info: $info, status: Status::Passed, result: $bench, attributes: ['duration' => 0]);
    }

    private static function makeInheritedResult(Status $status): TestResult
    {
        // Method declared on the abstract base, run through the concrete child:
        // ReflectionMethod::getDeclaringClass() resolves to AbstractSampleTest,
        // while the case reflection is the concrete ConcreteSampleTest.
        $reflection = new \ReflectionMethod(ConcreteSampleTest::class, 'inheritedTest');

        $info = new TestInfo(
            name: 'inheritedTest',
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Output/Unit'),
                definition: new CaseDefinition(
                    name: ConcreteSampleTest::class,
                    type: 'test',
                    file: Path::create(__FILE__),
                    reflection: new \ReflectionClass(ConcreteSampleTest::class),
                ),
            ),
            testDefinition: new TestDefinition($reflection),
        );

        return new TestResult(
            info: $info,
            status: $status,
            attributes: ['duration' => 0],
        );
    }

    private static function makeResultWithReflection(
        \ReflectionFunctionAbstract $reflection,
        Status $status,
    ): TestResult {
        $info = new TestInfo(
            name: $reflection->getShortName(),
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Output/Unit'),
                definition: new CaseDefinition(
                    name: null,
                    type: 'test',
                    file: Path::create(__FILE__),
                    reflection: null,
                ),
            ),
            testDefinition: new TestDefinition($reflection),
        );

        return new TestResult(
            info: $info,
            status: $status,
            attributes: ['duration' => 0],
        );
    }

    /**
     * Runs a {@see SampleTestClass} method and returns what it threw. Every frame past that method
     * (this helper, the test, the runner) stands for the framework code a real test is called through.
     *
     * @param non-empty-string $method
     */
    private static function thrownBy(string $method): \Throwable
    {
        try {
            (new SampleTestClass())->{$method}();
        } catch (\Throwable $e) {
            return $e;
        }
    }

    /**
     * @return list<string> The `#n ...` frame lines of one formatted exception.
     */
    private static function framesOf(string $details): array
    {
        return \array_values(\array_filter(
            \explode("\n", $details),
            static fn(string $line): bool => \str_starts_with($line, '#'),
        ));
    }

    private static function loadXml(string $raw): \SimpleXMLElement
    {
        $loaded = \simplexml_load_string($raw);
        Assert::notSame($loaded, false);
        \assert($loaded !== false);

        return $loaded;
    }

    private static function cleanup(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iter as $info) {
            $info->isDir() ? \rmdir($info->getPathname()) : \unlink($info->getPathname());
        }
        \rmdir($dir);
    }
}
