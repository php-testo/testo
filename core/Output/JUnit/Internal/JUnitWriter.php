<?php

declare(strict_types=1);

namespace Testo\Output\JUnit\Internal;

use Internal\Path;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Output\Rendering\BenchMapper;
use Testo\Output\Rendering\StackTrace;

/**
 * Builds and writes a JUnit XML (PHPUnit dialect) document.
 *
 * Maintains a stack of nested `<testsuite>` accumulators and a list of
 * `<testcase>` records inside the currently-open suite. Counters and time
 * are folded up to the parent on each suite close. The XML is emitted on
 * {@see write()} call.
 *
 * Status mapping rationale (see issue #122):
 * - `Risky` is reported as `<error type="Risky">`. PHPUnit and Codeception
 *   disagree on the encoding (`<error>` vs `<warning>`); `<error>` is in the
 *   JUnit XSD and is consumed correctly by GitHub Actions, GitLab, Jenkins.
 *   `<warning>` is non-standard and silently dropped by most consumers.
 * - `Flaky` is reported as a passing test (no child element); the test
 *   succeeded after retries, which most CI tools treat as a green test.
 *
 * @internal
 */
final class JUnitWriter
{
    /**
     * Namespace URI for Testo-specific attributes that travel inside the JUnit
     * XML. CI test reporters that don't recognise this prefix simply skip the
     * attributes; the Testo Infection bridge reads them via `getAttributeNS`
     * to recover provider/dataset coordinates that aren't in the standard
     * JUnit dialect (and aren't carried in the per-line coverage XML either).
     *
     * Mapped to the `testo:` prefix declared on the root `<testsuites>` element.
     */
    public const TESTO_NS = 'https://php-testo.github.io/schema/junit/1';

    /**
     * Top-level suites — children of the root `<testsuites>` node.
     *
     * @var list<JUnitSuiteNode>
     */
    private array $rootSuites = [];

    /**
     * Open suite nesting stack; the last element is where new test cases
     * and nested suites are attached.
     *
     * @var list<JUnitSuiteNode>
     */
    private array $stack = [];

    public function reset(): void
    {
        $this->rootSuites = [];
        $this->stack = [];
    }

    /**
     * Opens a new `<testsuite>` and pushes it onto the stack.
     *
     * @param non-empty-string $name
     * @param non-empty-string|null $file Optional source-file attribute,
     *        meaningful for the class-layer suite (Infection consumes it).
     */
    public function startSuite(string $name, ?string $file = null): void
    {
        $this->stack[] = new JUnitSuiteNode($name, $file);
    }

    /**
     * Closes the most recently opened `<testsuite>`. The closed suite is
     * folded into its parent (or the root list if no parent is open).
     */
    public function finishSuite(): void
    {
        if ($this->stack === []) {
            return;
        }

        /** @var JUnitSuiteNode $node */
        $node = \array_pop($this->stack);

        if ($this->stack === []) {
            $this->rootSuites[] = $node;
            return;
        }

        $parent = $this->stack[\array_key_last($this->stack)];
        $parent->children[] = $node;
    }

    /**
     * Records a `<testcase>` for a finished test result under the currently
     * open suite. If no suite is open, an implicit "default" root suite is
     * created to host the test case.
     *
     * @param non-empty-string|null $overrideName Replaces the test name (used for data-provider rows).
     * @param int|null $providerIndex Index of the data-provider attribute, when this row
     *        belongs to a data-provider batch. Stamped as `testo:provider-index`.
     *        Null providerIndex on a dataset row is normalised to `0` on emit so the
     *        attribute is always present alongside `testo:dataset-index`.
     * @param int|null $datasetIndex Zero-based dataset position within the provider.
     *        Stamped as `testo:dataset-index`. Setting this turns the testcase into
     *        a "dataset row" — the trio of testo: attributes is emitted only when
     *        this is non-null.
     * @param string|int|null $datasetKey The original dataset label (yield key).
     *        Stamped as `testo:dataset-key` for diagnostics.
     */
    public function addTestResult(
        TestResult $result,
        ?string $overrideName = null,
        ?int $providerIndex = null,
        ?int $datasetIndex = null,
        string|int|null $datasetKey = null,
    ): void {
        $info = $result->info;
        $name = $overrideName ?? $info->name;
        $duration = (int) $result->getAttribute('duration');
        $time = $duration / 1000.0;

        $reflection = $info->testDefinition->reflection;
        $file = $reflection->getFileName();
        $file = $file === false ? null : $file;
        $line = $reflection->getStartLine();
        $line = $line === false ? null : $line;

        $case = new JUnitCaseNode(
            name: $name,
            classname: self::classnameFor($info),
            file: $file,
            line: $line,
            time: $time,
            status: $result->status,
            outcome: self::outcomeFor($result),
            providerIndex: $providerIndex,
            datasetIndex: $datasetIndex,
            datasetKey: $datasetKey,
            properties: BenchMapper::supports($result->result)
                ? BenchMapper::metrics($result->result)
                : [],
        );

        $suite = $this->currentSuite();
        $suite->cases[] = $case;
        $suite->tests++;
        $suite->time += $time;

        match ($result->status) {
            Status::Failed => $suite->failures++,
            Status::Error, Status::Aborted, Status::Risky => $suite->errors++,
            Status::Skipped, Status::Cancelled => $suite->skipped++,
            Status::Passed, Status::Flaky => null,
        };
    }

    /**
     * Generates the XML document and writes it to disk. Parent directories
     * are created on demand.
     *
     * @param non-empty-string $rootName
     */
    public function write(Path $path, string $rootName): void
    {
        $dir = (string) $path->parent();
        \is_dir($dir) or \mkdir($dir, 0o755, true) or throw new \RuntimeException("Failed to create directory: {$dir}");

        \file_put_contents((string) $path, $this->generate($rootName));
    }

    /**
     * @param non-empty-string $rootName
     */
    public function generate(string $rootName): string
    {
        // Auto-close any suites left open (defensive; SessionFinished may fire
        // mid-execution if a suite/case did not close cleanly).
        while ($this->stack !== []) {
            $this->finishSuite();
        }

        $totals = $this->rollup();

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('testsuites');
        // Declared once on root so per-testcase emission stays terse and so
        // CI consumers see a single namespace declaration to skip over.
        $xml->writeAttribute('xmlns:testo', self::TESTO_NS);
        $xml->writeAttribute('name', $rootName);
        $xml->writeAttribute('tests', (string) $totals['tests']);
        $xml->writeAttribute('failures', (string) $totals['failures']);
        $xml->writeAttribute('errors', (string) $totals['errors']);
        $xml->writeAttribute('skipped', (string) $totals['skipped']);
        $xml->writeAttribute('time', self::formatTime($totals['time']));

        foreach ($this->rootSuites as $suite) {
            $this->writeSuite($xml, $suite);
        }

        $xml->endElement(); // testsuites
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Aggregates counters of a suite together with its descendants, mutating
     * the node so the rolled-up values are written into XML.
     *
     * @return array{tests: int, failures: int, errors: int, skipped: int, time: float}
     */
    private static function rollupSuite(JUnitSuiteNode $suite): array
    {
        $tests = $suite->tests;
        $failures = $suite->failures;
        $errors = $suite->errors;
        $skipped = $suite->skipped;
        $time = $suite->time;

        foreach ($suite->children as $child) {
            $childTotals = self::rollupSuite($child);
            $tests += $childTotals['tests'];
            $failures += $childTotals['failures'];
            $errors += $childTotals['errors'];
            $skipped += $childTotals['skipped'];
            $time += $childTotals['time'];
        }

        $suite->totalTests = $tests;
        $suite->totalFailures = $failures;
        $suite->totalErrors = $errors;
        $suite->totalSkipped = $skipped;
        $suite->totalTime = $time;

        return [
            'tests' => $tests,
            'failures' => $failures,
            'errors' => $errors,
            'skipped' => $skipped,
            'time' => $time,
        ];
    }

    /**
     * @return non-empty-string
     */
    private static function classnameFor(TestInfo $info): string
    {
        // Class-bound tests: the address already names the concrete (runtime) case class rather than
        // the method's declaring class. For a `#[Test]` inherited from an abstract base,
        // getDeclaringClass() would name the base — but the enclosing <testsuite> is named after the
        // concrete subclass (see JUnitPlugin), and Infection joins coverage to a test file by matching
        // this classname against that suite name. Diverging the two breaks the lookup.
        //
        // Free-function test: no class to name, so the function's own FQN stands in, matching the
        // per-function synthetic <testsuite name="..."> opened by JUnitPlugin.
        return $info->identity->case ?? $info->identity->qualifiedName();
    }

    private static function outcomeFor(TestResult $result): ?JUnitCaseOutcome
    {
        return match ($result->status) {
            Status::Passed, Status::Flaky => null,

            Status::Failed => new JUnitCaseOutcome(
                element: 'failure',
                type: $result->failure !== null ? $result->failure::class : 'Failure',
                message: $result->failure?->getMessage() ?? 'Test failed',
                details: self::formatTrace($result),
            ),

            Status::Error => new JUnitCaseOutcome(
                element: 'error',
                type: $result->failure !== null ? $result->failure::class : 'Error',
                message: $result->failure?->getMessage() ?? 'Test errored',
                details: self::formatTrace($result),
            ),

            Status::Aborted => new JUnitCaseOutcome(
                element: 'error',
                type: 'Aborted',
                message: $result->failure?->getMessage() ?? 'Test aborted',
                details: self::formatTrace($result),
            ),

            Status::Risky => new JUnitCaseOutcome(
                element: 'error',
                type: 'Risky',
                message: $result->failure?->getMessage() ?? 'Test marked as risky',
                details: self::formatTrace($result),
            ),

            Status::Skipped => new JUnitCaseOutcome(
                element: 'skipped',
                type: 'Skipped',
                message: $result->failure?->getMessage() ?? '',
                details: '',
            ),

            Status::Cancelled => new JUnitCaseOutcome(
                element: 'skipped',
                type: 'Cancelled',
                message: $result->failure?->getMessage() ?? 'Test cancelled',
                details: '',
            ),
        };
    }

    private static function formatTrace(TestResult $result): string
    {
        $failure = $result->failure;
        if ($failure === null) {
            return '';
        }

        $boundary = $result->info->testDefinition->reflection;
        $parts = [];
        $current = $failure;

        do {
            $class = $current::class;
            $file = $current->getFile();
            $line = $current->getLine();
            $trace = self::formatTraceFrames(StackTrace::cutStackTrace($current->getTrace(), $boundary, false));

            $parts[] = "{$class}\nFile: {$file}:{$line}\n\nStack trace:\n{$trace}";
        } while ($current = $current->getPrevious());

        return \implode("\n\nCaused by:\n", $parts);
    }

    /**
     * @param list<array<string, mixed>> $trace
     */
    private static function formatTraceFrames(array $trace): string
    {
        $lines = [];

        foreach ($trace as $i => $frame) {
            $location = isset($frame['file'])
                ? "{$frame['file']}({$frame['line']})"
                : '[internal function]';
            $call = isset($frame['class'])
                ? "{$frame['class']}{$frame['type']}{$frame['function']}()"
                : "{$frame['function']}()";
            $lines[] = "#{$i} {$location}: {$call}";
        }

        return \implode("\n", $lines);
    }

    private static function formatTime(float $seconds): string
    {
        return \sprintf('%.6F', $seconds);
    }

    /**
     * Wraps text in CDATA, escaping any literal `]]>` occurrences so the
     * payload survives intact.
     */
    private static function escapeCdata(string $text): string
    {
        $safe = \str_replace(']]>', ']]]]><![CDATA[>', $text);
        return '<![CDATA[' . $safe . ']]>';
    }

    /**
     * @return array{tests: int, failures: int, errors: int, skipped: int, time: float}
     */
    private function rollup(): array
    {
        $tests = 0;
        $failures = 0;
        $errors = 0;
        $skipped = 0;
        $time = 0.0;

        foreach ($this->rootSuites as $suite) {
            $totals = self::rollupSuite($suite);
            $tests += $totals['tests'];
            $failures += $totals['failures'];
            $errors += $totals['errors'];
            $skipped += $totals['skipped'];
            $time += $totals['time'];
        }

        return [
            'tests' => $tests,
            'failures' => $failures,
            'errors' => $errors,
            'skipped' => $skipped,
            'time' => $time,
        ];
    }

    private function writeSuite(\XMLWriter $xml, JUnitSuiteNode $suite): void
    {
        $xml->startElement('testsuite');
        $xml->writeAttribute('name', $suite->name);
        $suite->file === null or $xml->writeAttribute('file', $suite->file);
        $xml->writeAttribute('tests', (string) $suite->totalTests);
        $xml->writeAttribute('failures', (string) $suite->totalFailures);
        $xml->writeAttribute('errors', (string) $suite->totalErrors);
        $xml->writeAttribute('skipped', (string) $suite->totalSkipped);
        $xml->writeAttribute('time', self::formatTime($suite->totalTime));

        foreach ($suite->children as $child) {
            $this->writeSuite($xml, $child);
        }

        foreach ($suite->cases as $case) {
            $this->writeCase($xml, $case);
        }

        $xml->endElement(); // testsuite
    }

    private function writeCase(\XMLWriter $xml, JUnitCaseNode $case): void
    {
        $xml->startElement('testcase');
        $xml->writeAttribute('name', $case->name);
        $case->classname === '' or $xml->writeAttribute('classname', $case->classname);
        $case->file !== null and $xml->writeAttribute('file', $case->file);
        $case->line !== null and $xml->writeAttribute('line', (string) $case->line);
        $xml->writeAttribute('time', self::formatTime($case->time));

        // Testo-private dataset coordinates. Only present on data-provider rows
        // (datasetIndex != null). The CLI accepts `Class::method:provider:dataset`
        // — null providerIndex on a dataset row collapses to 0 to keep the pair
        // consumable without conditional logic on the reader side.
        if ($case->datasetIndex !== null) {
            $xml->writeAttribute('testo:data-provider', (string) ($case->providerIndex ?? 0));
            $xml->writeAttribute('testo:data-set', (string) $case->datasetIndex);
            $case->datasetKey === null or $xml->writeAttribute('testo:data-set-key', (string) $case->datasetKey);
        }

        // Measurements a plain testcase has nowhere else to carry. `<properties>` under `<testcase>` is
        // the shape PHPUnit emits and CI servers already read, so a consumer needs no Testo-specific
        // knowledge to pick the numbers up.
        if ($case->properties !== []) {
            $xml->startElement('properties');
            foreach ($case->properties as $name => $value) {
                $xml->startElement('property');
                $xml->writeAttribute('name', $name);
                $xml->writeAttribute('value', BenchMapper::formatMetric($value));
                $xml->endElement();
            }
            $xml->endElement();
        }

        $outcome = $case->outcome;
        if ($outcome !== null) {
            if ($outcome->element === 'skipped') {
                $xml->startElement('skipped');
                $outcome->message === '' or $xml->writeAttribute('message', $outcome->message);
                $xml->endElement();
            } else {
                $xml->startElement($outcome->element);
                $xml->writeAttribute('type', $outcome->type);
                $outcome->message === '' or $xml->writeAttribute('message', $outcome->message);
                $outcome->details === '' or $xml->writeRaw(self::escapeCdata($outcome->details));
                $xml->endElement();
            }
        }

        $xml->endElement(); // testcase
    }

    private function currentSuite(): JUnitSuiteNode
    {
        if ($this->stack === []) {
            // No suite open — host orphan tests in an implicit root suite so
            // the document remains well-formed.
            $synthetic = new JUnitSuiteNode('default');
            $this->stack[] = $synthetic;
        }

        return $this->stack[\array_key_last($this->stack)];
    }
}
