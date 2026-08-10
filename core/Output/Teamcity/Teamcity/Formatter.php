<?php

declare(strict_types=1);

namespace Testo\Output\Teamcity\Teamcity;

use Testo\Core\Context\Identity;
use Testo\Core\Context\Identity\CaseIdentity;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\Identity\TestIdentity;
use Testo\Core\Value\Status;

/**
 * Formats TeamCity service messages.
 *
 * Generates TeamCity-compatible service message strings.
 * Messages follow the format: ##teamcity[messageName name='value' attr='value']
 *
 * All methods are static as this class has no state.
 *
 * @link https://www.jetbrains.com/help/teamcity/service-messages.html
 * @internal
 */
final class Formatter
{
    /**
     * Node every top-level one hangs under, as the IntelliJ id-based protocol fixes it.
     *
     * @see https://github.com/JetBrains/intellij-community/blob/master/platform/smRunner/src/com/intellij/execution/testframework/sm/runner/events/TreeNodeEvent.java
     */
    private const ROOT_NODE = '0';

    private function __construct() {}

    /**
     * TeamCity `flowId` for a test: distinct concurrent tests get distinct flows, so their interleaved
     * `testStarted`/output/`testFinished` messages stay grouped instead of overlapping on one stream.
     *
     * A data set answers its batch's flow rather than one of its own — the batch opened a nested suite
     * that its data sets report inside, and a flow of their own would leave that suite behind.
     *
     * @return non-empty-string
     */
    public static function flowId(TestIdentity $identity): string
    {
        return (string) $identity->pipelineId;
    }

    /**
     * Formats a test suite started message.
     *
     * @param non-empty-string $name Suite name
     * @param Identity|null $identity Address of the node this message opens — a suite of the run, a
     *        case, or the test behind a DataProvider batch node. {@see placement()}
     * @return non-empty-string
     */
    public static function suiteStarted(string $name, ?Identity $identity = null): string
    {
        $attributes = ['name' => $name];

        $locationHint = self::locationHint($identity);
        $locationHint === null or $attributes['locationHint'] = $locationHint;

        return self::formatMessage(
            'testSuiteStarted',
            $attributes + self::taxonomy($identity) + self::placement($identity),
        );
    }

    /**
     * Formats a message announcing how many tests are about to run.
     *
     * Feeds the progress bar of IntelliJ-based IDEs, which is the only consumer — the TeamCity server
     * ignores it. Counts accumulate rather than replace, so one message per suite is the intended
     * shape rather than a single total up front.
     *
     * @param int<0, max> $count
     * @return non-empty-string
     */
    public static function testCount(int $count): string
    {
        return self::formatMessage('testCount', ['count' => (string) $count]);
    }

    /**
     * Formats a test suite finished message.
     *
     * @param non-empty-string $name Suite name
     * @param Identity|null $identity Address of the node this message closes. {@see placement()}
     * @param Status|null $status Aggregated outcome of the node. {@see status()}
     * @return non-empty-string
     */
    public static function suiteFinished(string $name, ?Identity $identity = null, ?Status $status = null): string
    {
        return self::formatMessage(
            'testSuiteFinished',
            ['name' => $name] + self::status($status) + self::placement($identity),
        );
    }

    /**
     * Formats a test started message.
     *
     * @param non-empty-string $name Test name
     * @param bool $captureStandardOutput Whether to capture standard output
     * @param TestIdentity|null $identity Address to point the location hint at. When it addresses a data
     *        set, the hint carries the coordinates — no separate suffix is needed.
     * @param non-empty-string|null $description Test description (from the PHPDoc summary), emitted as
     *        the TeamCity `metainfo` attribute. Omitted when `null`.
     * @return non-empty-string
     */
    public static function testStarted(string $name, bool $captureStandardOutput = false, ?TestIdentity $identity = null, ?string $description = null): string
    {
        $attributes = ['name' => $name];

        $captureStandardOutput and $attributes['captureStandardOutput'] = 'true';

        $locationHint = self::locationHint($identity);
        $locationHint === null or $attributes['locationHint'] = $locationHint;

        $description !== null and $attributes['metainfo'] = $description;

        return self::formatMessage(
            'testStarted',
            $attributes + self::taxonomy($identity) + self::placement($identity),
        );
    }

    /**
     * Formats a test finished message.
     *
     * @param non-empty-string $name Test name
     * @param int<0, max>|null $duration Duration in milliseconds
     * @param TestIdentity|null $identity Address of the test this message closes. {@see placement()}
     * @param Status|null $status Outcome of the test. {@see status()}
     * @param int<0, max>|null $assertions Number of assertions the test performed. Null when nothing
     *        counted them — no assertion plugin is active — which is not the same as a test that
     *        counted zero.
     * @return non-empty-string
     */
    public static function testFinished(
        string $name,
        ?int $duration = null,
        ?TestIdentity $identity = null,
        ?Status $status = null,
        ?int $assertions = null,
    ): string {
        $attributes = ['name' => $name];

        $duration !== null and $attributes['duration'] = (string) $duration;
        $assertions !== null and $attributes['assertions'] = (string) $assertions;

        return self::formatMessage('testFinished', $attributes + self::status($status) + self::placement($identity));
    }

    /**
     * Formats a test failed message.
     *
     * @param non-empty-string $name Test name
     * @param non-empty-string $message Failure message
     * @param non-empty-string $details Detailed failure information (stack trace, etc.)
     * @param non-empty-string|null $type Comparison type for diff display (e.g., 'comparisonFailure')
     * @param non-empty-string|null $expected Expected value for diff
     * @param non-empty-string|null $actual Actual value for diff
     * @param TestIdentity|null $identity Address of the test that failed. {@see placement()}
     * @return non-empty-string
     */
    public static function testFailed(
        string $name,
        string $message,
        string $details = '',
        ?string $type = null,
        ?string $expected = null,
        ?string $actual = null,
        ?TestIdentity $identity = null,
    ): string {
        $attributes = [
            'name' => $name,
            'message' => $message,
            'details' => $details,
        ];

        $type !== null and $attributes['type'] = $type;
        $expected !== null and $attributes['expected'] = $expected;
        $actual !== null and $attributes['actual'] = $actual;

        return self::formatMessage('testFailed', $attributes + self::placement($identity));
    }

    /**
     * Formats a test ignored/skipped message.
     *
     * @param non-empty-string $name Test name
     * @param non-empty-string $message Optional skip reason
     * @param TestIdentity|null $identity Address of the test that was skipped. {@see placement()}
     * @return non-empty-string
     */
    public static function testIgnored(string $name, string $message = '', ?TestIdentity $identity = null): string
    {
        $attributes = ['name' => $name];

        $message !== '' and $attributes['message'] = $message;

        return self::formatMessage('testIgnored', $attributes + self::placement($identity));
    }

    /**
     * Formats a test standard output message.
     *
     * @param non-empty-string $name Test name
     * @param non-empty-string $output Standard output content
     * @param array<non-empty-string, string> $attributes Extra attributes (e.g. `channel`, `level`)
     *        for consumers that understand them; standard TeamCity parsers ignore unknown ones.
     * @param TestIdentity|null $identity Address of the test the output came from. {@see placement()}
     * @return non-empty-string
     */
    public static function testStdOut(string $name, string $output, array $attributes = [], ?TestIdentity $identity = null): string
    {
        return self::formatMessage('testStdOut', [
            'name' => $name,
            'out' => $output,
        ] + $attributes + self::placement($identity));
    }

    /**
     * Formats a test standard error message.
     *
     * @param non-empty-string $name Test name
     * @param non-empty-string $output Standard error content
     * @param array<non-empty-string, string> $attributes Extra attributes (e.g. `channel`, `level`)
     *        for consumers that understand them; standard TeamCity parsers ignore unknown ones.
     * @param Identity|null $identity Address of the node the output came from — a test, or the suite or
     *        case whose own failure this reports. {@see placement()}
     * @return non-empty-string
     */
    public static function testStdErr(string $name, string $output, array $attributes = [], ?Identity $identity = null): string
    {
        return self::formatMessage('testStdErr', [
            'name' => $name,
            'out' => $output,
        ] + $attributes + self::placement($identity));
    }

    /**
     * Formats a progress message.
     *
     * @param non-empty-string $message Progress message
     * @return non-empty-string
     */
    public static function progressMessage(string $message): string
    {
        return self::formatMessage('progressMessage', ['text' => $message]);
    }

    /**
     * Formats a progress start message.
     *
     * @param non-empty-string $message Progress message
     * @return non-empty-string
     */
    public static function progressStart(string $message): string
    {
        return self::formatMessage('progressStart', ['text' => $message]);
    }

    /**
     * Formats a progress finish message.
     *
     * @param non-empty-string $message Progress message
     * @return non-empty-string
     */
    public static function progressFinish(string $message): string
    {
        return self::formatMessage('progressFinish', ['text' => $message]);
    }

    /**
     * Formats a build problem message.
     *
     * @param non-empty-string $description Problem description
     * @param non-empty-string|null $identity Problem identity for deduplication
     * @return non-empty-string
     */
    public static function buildProblem(string $description, ?string $identity = null): string
    {
        $attributes = ['description' => $description];

        $identity !== null and $attributes['identity'] = $identity;

        return self::formatMessage('buildProblem', $attributes);
    }

    /**
     * Formats a build status message.
     *
     * @param non-empty-string $text Status text
     * @param 'FAILURE'|'SUCCESS'|null $status Build status
     * @return non-empty-string
     */
    public static function buildStatus(string $text, ?string $status = null): string
    {
        $attributes = ['text' => $text];

        $status !== null and $attributes['status'] = $status;

        return self::formatMessage('buildStatus', $attributes);
    }

    /**
     * Formats a block opened message for grouping output.
     *
     * @param non-empty-string $name Block name
     * @return non-empty-string
     */
    public static function blockOpened(string $name): string
    {
        return self::formatMessage('blockOpened', ['name' => $name]);
    }

    /**
     * Formats a block closed message.
     *
     * @param non-empty-string $name Block name
     * @return non-empty-string
     */
    public static function blockClosed(string $name): string
    {
        return self::formatMessage('blockClosed', ['name' => $name]);
    }

    /**
     * Formats a build parameter message.
     *
     * @param non-empty-string $name Parameter name
     * @param non-empty-string $value Parameter value
     * @return non-empty-string
     */
    public static function buildParameter(string $name, string $value): string
    {
        return self::formatMessage('setParameter', ['name' => $name, 'value' => $value]);
    }

    /**
     * Formats a message for display in the build log.
     *
     * @param non-empty-string $text Message text
     * @param 'NORMAL'|'WARNING'|'FAILURE'|'ERROR' $status Message status
     * @return non-empty-string
     */
    public static function message(string $text, string $status = 'NORMAL'): string
    {
        return self::formatMessage('message', ['text' => $text, 'status' => $status]);
    }

    /**
     * Formats a compilation started message.
     *
     * @param non-empty-string $compiler Compiler name
     * @return non-empty-string
     */
    public static function compilationStarted(string $compiler): string
    {
        return self::formatMessage('compilationStarted', ['compiler' => $compiler]);
    }

    /**
     * Formats a compilation finished message.
     *
     * @param non-empty-string $compiler Compiler name
     * @return non-empty-string
     */
    public static function compilationFinished(string $compiler): string
    {
        return self::formatMessage('compilationFinished', ['compiler' => $compiler]);
    }

    /**
     * Formats a TeamCity service message.
     *
     * @param non-empty-string $messageName Message type name
     * @param array<non-empty-string, string> $attributes Message attributes
     * @return non-empty-string
     */
    private static function formatMessage(string $messageName, array $attributes): string
    {
        $formattedAttributes = self::formatAttributes($attributes);
        return "##teamcity[{$messageName}{$formattedAttributes}]";
    }

    /**
     * Formats message attributes.
     *
     * @param array<non-empty-string, string> $attributes
     * @return string Formatted attributes string (may be empty)
     */
    private static function formatAttributes(array $attributes): string
    {
        if ($attributes === []) {
            return '';
        }

        $parts = [];
        foreach ($attributes as $key => $value) {
            $escapedValue = self::escape($value);
            $parts[] = " {$key}='{$escapedValue}'";
        }

        return \implode('', $parts);
    }

    /**
     * Escapes a value for TeamCity service messages.
     *
     * Special characters that need escaping:
     * - ' (apostrophe) -> |'
     * - \n (newline) -> |n
     * - \r (carriage return) -> |r
     * - | (pipe) -> ||
     * - [ (opening bracket) -> |[
     * - ] (closing bracket) -> |]
     * - Unicode characters 0x0000-0x001F -> |0x<code>
     */
    private static function escape(string $value): string
    {
        return \str_replace(
            ["|", "'", "\n", "\r", "[", "]"],
            ["||", "|'", "|n", "|r", "|[", "|]"],
            $value,
        );
    }

    /**
     * Where in the run a message belongs: which node it is about, and which flow carries it.
     *
     * `nodeId`/`parentNodeId` state the tree outright, which is the only way a consumer gets it right
     * when tests run concurrently — one that nests by whatever opened last puts an interleaved batch's
     * node inside its neighbour's. The ids are the run numbers off the address
     * ({@see Identity::$runtimeId}, {@see Identity::$parentId}), so a node keeps its identity across
     * every message about it, and a level with no parent hangs under {@see ROOT_NODE}.
     *
     * `flowId` is TeamCity's own grouping and applies to tests only: suites and cases of one process
     * never overlap, so there is nothing to tell apart. {@see flowId()}
     *
     * @return array<non-empty-string, string>
     */
    private static function placement(?Identity $identity): array
    {
        if ($identity === null) {
            return [];
        }

        $placement = [
            'nodeId' => (string) $identity->runtimeId,
            'parentNodeId' => (string) ($identity->parentId ?? self::ROOT_NODE),
        ];

        $identity instanceof TestIdentity and $placement['flowId'] = self::flowId($identity);

        return $placement;
    }

    /**
     * Which suite the node belongs to and which kind of test it holds — the two things `--suite` and
     * `--type` select on, so a consumer can offer the same slicing without parsing anything out of a
     * name or a path.
     *
     * Only stated where the address knows it: a suite of the run has no type of its own, since one
     * suite can hold cases of several ({@see CaseIdentity::$type}).
     *
     * @return array<non-empty-string, non-empty-string>
     */
    private static function taxonomy(?Identity $identity): array
    {
        return match (true) {
            $identity instanceof CaseIdentity,
            $identity instanceof TestIdentity => [
                'testSuite' => $identity->suite,
                'testType' => $identity->type,
            ],
            $identity instanceof SuiteIdentity => ['testSuite' => $identity->suite],
            default => [],
        };
    }

    /**
     * The exact outcome, which the standard protocol cannot express: it distinguishes only ignored,
     * failed and everything else, so `Flaky` is indistinguishable from `Passed` and `Risky` from a
     * clean pass. Consumers that understand the attribute get the {@see Status} verbatim; standard
     * parsers ignore it and keep reading the run as before.
     *
     * Lowercased case name, the same wire format the JSON report speaks.
     *
     * @return array<non-empty-string, non-empty-string>
     */
    private static function status(?Status $status): array
    {
        return $status === null ? [] : ['status' => \strtolower($status->name)];
    }

    /**
     * Location hint for whatever the address names.
     *
     * ```
     * php_qn://path/to/BarTest.php::\Ns\BarTest                 a case
     * php_qn://path/to/BarTest.php::\Ns\BarTest::itWorks        a test, or its DataProvider batch node
     * php_qn://path/to/BarTest.php::\Ns\BarTest::itWorks:0:1    one data set of it
     * php_qn://path/to/functions.php::\Ns\itWorksToo            a free test function
     * file://path/to/functions.php                             a case of free functions
     * ```
     *
     * The tail is {@see TestIdentity::fqn()} verbatim, so a hint pastes straight back into `--filter`.
     *
     * A case of free functions names no class, and the file it groups holds several functions rather
     * than one to point at — so it answers with the file itself under `file://`, the scheme IDEs
     * resolve to a whole file. Clickable all the same, which a hintless node is not.
     *
     * Null only for a suite of the run: it is a configuration entry, with no file of its own to name.
     *
     * @return non-empty-string|null
     */
    private static function locationHint(?Identity $identity): ?string
    {
        if (!$identity instanceof CaseIdentity && !$identity instanceof TestIdentity) {
            return null;
        }

        $fqn = $identity->fqn();

        return $fqn === null ? "file://{$identity->file}" : "php_qn://{$identity->file}::\\{$fqn}";
    }
}
