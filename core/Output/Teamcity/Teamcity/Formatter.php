<?php

declare(strict_types=1);

namespace Testo\Output\Teamcity\Teamcity;

use Testo\Core\Context\Identity\CaseIdentity;
use Testo\Core\Context\Identity\TestIdentity;

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
    private function __construct() {}

    /**
     * Formats a test suite started message.
     *
     * @param non-empty-string $name Suite name
     * @param CaseIdentity|TestIdentity|null $identity Address to point the location hint at — a case, or
     *        the test behind a DataProvider batch node. Omitted for the run's own suites, which are
     *        configuration entries rather than code.
     * @param non-empty-string|null $flowId Flow this suite belongs to; groups its messages apart from
     *        other flows running concurrently on the same stream.
     * @return non-empty-string
     */
    public static function suiteStarted(string $name, CaseIdentity|TestIdentity|null $identity = null, ?string $flowId = null): string
    {
        $attributes = ['name' => $name];

        $locationHint = $identity === null ? null : self::locationHint($identity);
        $locationHint === null or $attributes['locationHint'] = $locationHint;

        $flowId !== null and $attributes['flowId'] = $flowId;

        return self::formatMessage('testSuiteStarted', $attributes);
    }

    /**
     * Formats a test suite finished message.
     *
     * @param non-empty-string $name Suite name
     * @param non-empty-string|null $flowId Flow this suite belongs to.
     * @return non-empty-string
     */
    public static function suiteFinished(string $name, ?string $flowId = null): string
    {
        $attributes = ['name' => $name];

        $flowId !== null and $attributes['flowId'] = $flowId;

        return self::formatMessage('testSuiteFinished', $attributes);
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
     * @param non-empty-string|null $flowId Flow this test belongs to; keeps its messages grouped apart
     *        from other tests running concurrently on the same stream.
     * @return non-empty-string
     */
    public static function testStarted(string $name, bool $captureStandardOutput = false, ?TestIdentity $identity = null, ?string $description = null, ?string $flowId = null): string
    {
        $attributes = ['name' => $name];

        $captureStandardOutput and $attributes['captureStandardOutput'] = 'true';

        $locationHint = $identity === null ? null : self::locationHint($identity);
        $locationHint === null or $attributes['locationHint'] = $locationHint;

        $description !== null and $attributes['metainfo'] = $description;
        $flowId !== null and $attributes['flowId'] = $flowId;

        return self::formatMessage('testStarted', $attributes);
    }

    /**
     * Formats a test finished message.
     *
     * @param non-empty-string $name Test name
     * @param int<0, max>|null $duration Duration in milliseconds
     * @param non-empty-string|null $flowId Flow this test belongs to.
     * @return non-empty-string
     */
    public static function testFinished(string $name, ?int $duration = null, ?string $flowId = null): string
    {
        $attributes = ['name' => $name];

        $duration !== null and $attributes['duration'] = (string) $duration;
        $flowId !== null and $attributes['flowId'] = $flowId;

        return self::formatMessage('testFinished', $attributes);
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
     * @param non-empty-string|null $flowId Flow this test belongs to.
     * @return non-empty-string
     */
    public static function testFailed(
        string $name,
        string $message,
        string $details = '',
        ?string $type = null,
        ?string $expected = null,
        ?string $actual = null,
        ?string $flowId = null,
    ): string {
        $attributes = [
            'name' => $name,
            'message' => $message,
            'details' => $details,
        ];

        $type !== null and $attributes['type'] = $type;
        $expected !== null and $attributes['expected'] = $expected;
        $actual !== null and $attributes['actual'] = $actual;
        $flowId !== null and $attributes['flowId'] = $flowId;

        return self::formatMessage('testFailed', $attributes);
    }

    /**
     * Formats a test ignored/skipped message.
     *
     * @param non-empty-string $name Test name
     * @param non-empty-string $message Optional skip reason
     * @param non-empty-string|null $flowId Flow this test belongs to.
     * @return non-empty-string
     */
    public static function testIgnored(string $name, string $message = '', ?string $flowId = null): string
    {
        $attributes = ['name' => $name];

        $message !== '' and $attributes['message'] = $message;
        $flowId !== null and $attributes['flowId'] = $flowId;

        return self::formatMessage('testIgnored', $attributes);
    }

    /**
     * Formats a test standard output message.
     *
     * @param non-empty-string $name Test name
     * @param non-empty-string $output Standard output content
     * @param array<non-empty-string, string> $attributes Extra attributes (e.g. `channel`, `level`)
     *        for consumers that understand them; standard TeamCity parsers ignore unknown ones.
     * @param non-empty-string|null $flowId Flow the emitting test belongs to.
     * @return non-empty-string
     */
    public static function testStdOut(string $name, string $output, array $attributes = [], ?string $flowId = null): string
    {
        $flowId !== null and $attributes['flowId'] = $flowId;

        return self::formatMessage('testStdOut', [
            'name' => $name,
            'out' => $output,
        ] + $attributes);
    }

    /**
     * Formats a test standard error message.
     *
     * @param non-empty-string $name Test name
     * @param non-empty-string $output Standard error content
     * @param array<non-empty-string, string> $attributes Extra attributes (e.g. `channel`, `level`)
     *        for consumers that understand them; standard TeamCity parsers ignore unknown ones.
     * @param non-empty-string|null $flowId Flow the emitting test belongs to.
     * @return non-empty-string
     */
    public static function testStdErr(string $name, string $output, array $attributes = [], ?string $flowId = null): string
    {
        $flowId !== null and $attributes['flowId'] = $flowId;

        return self::formatMessage('testStdErr', [
            'name' => $name,
            'out' => $output,
        ] + $attributes);
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
     * Location hint for whatever the address names.
     *
     * ```
     * php_qn://path/to/BarTest.php::\Ns\BarTest                 a case
     * php_qn://path/to/BarTest.php::\Ns\BarTest::itWorks        a test, or its DataProvider batch node
     * php_qn://path/to/BarTest.php::\Ns\BarTest::itWorks:0:1    one data set of it
     * php_qn://path/to/functions.php::\Ns\itWorksToo            a free test function
     * ```
     *
     * The tail is {@see TestIdentity::fqn()} verbatim, so a hint pastes straight back into `--filter`.
     * It used to be assembled from reflection here, with a data set addressed by its *key* — which
     * collides whenever a provider repeats one, and which `--filter` does not accept.
     *
     * Null when there is nothing to point at: no file (a case built by hand rather than located), or no
     * code (a case of free functions, which has no class of its own).
     *
     * @return non-empty-string|null
     */
    private static function locationHint(CaseIdentity|TestIdentity $identity): ?string
    {
        $fqn = $identity->fqn();

        return $identity->file === null || $fqn === null
            ? null
            : "php_qn://{$identity->file}::\\{$fqn}";
    }
}
