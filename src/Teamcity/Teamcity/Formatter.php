<?php

declare(strict_types=1);

namespace Testo\Teamcity\Teamcity;

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
     * @param \ReflectionClass<object>|null $reflection Class reflection for location hint
     * @return non-empty-string
     */
    public static function suiteStarted(string $name, null|\ReflectionClass|\ReflectionFunctionAbstract $reflection = null): string
    {
        $attributes = ['name' => $name];

        if ($reflection !== null) {
            $locationHint = $reflection instanceof \ReflectionClass
                ? self::caseLocationHint($reflection)
                : self::testLocationHint($reflection);
            $locationHint !== null and $attributes['locationHint'] = $locationHint;
        }

        return self::formatMessage('testSuiteStarted', $attributes);
    }

    /**
     * Formats a test suite finished message.
     *
     * @param non-empty-string $name Suite name
     * @return non-empty-string
     */
    public static function suiteFinished(string $name): string
    {
        return self::formatMessage('testSuiteFinished', ['name' => $name]);
    }

    /**
     * Formats a test started message.
     *
     * @param non-empty-string $name Test name
     * @param bool $captureStandardOutput Whether to capture standard output
     * @param \ReflectionFunctionAbstract|null $reflection Function/method reflection for location hint
     * @param non-empty-string|null $locationSuffix Optional suffix to append to location hint (e.g., " with data set #0")
     * @return non-empty-string
     */
    public static function testStarted(string $name, bool $captureStandardOutput = false, ?\ReflectionFunctionAbstract $reflection = null, ?string $locationSuffix = null): string
    {
        $attributes = ['name' => $name];

        $captureStandardOutput and $attributes['captureStandardOutput'] = 'true';

        if ($reflection !== null) {
            $locationHint = self::testLocationHint($reflection, $locationSuffix);
            $locationHint !== null and $attributes['locationHint'] = $locationHint;
        }

        return self::formatMessage('testStarted', $attributes);
    }

    /**
     * Formats a test finished message.
     *
     * @param non-empty-string $name Test name
     * @param int<0, max>|null $duration Duration in milliseconds
     * @return non-empty-string
     */
    public static function testFinished(string $name, ?int $duration = null): string
    {
        $attributes = ['name' => $name];

        $duration !== null and $attributes['duration'] = (string) $duration;

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
     * @return non-empty-string
     */
    public static function testFailed(
        string $name,
        string $message,
        string $details = '',
        ?string $type = null,
        ?string $expected = null,
        ?string $actual = null,
    ): string {
        $attributes = [
            'name' => $name,
            'message' => $message,
            'details' => $details,
        ];

        $type !== null and $attributes['type'] = $type;
        $expected !== null and $attributes['expected'] = $expected;
        $actual !== null and $attributes['actual'] = $actual;

        return self::formatMessage('testFailed', $attributes);
    }

    /**
     * Formats a test ignored/skipped message.
     *
     * @param non-empty-string $name Test name
     * @param non-empty-string $message Optional skip reason
     * @return non-empty-string
     */
    public static function testIgnored(string $name, string $message = ''): string
    {
        $attributes = ['name' => $name];

        $message !== '' and $attributes['message'] = $message;

        return self::formatMessage('testIgnored', $attributes);
    }

    /**
     * Formats a test standard output message.
     *
     * @param non-empty-string $name Test name
     * @param non-empty-string $output Standard output content
     * @return non-empty-string
     */
    public static function testStdOut(string $name, string $output): string
    {
        return self::formatMessage('testStdOut', [
            'name' => $name,
            'out' => $output,
        ]);
    }

    /**
     * Formats a test standard error message.
     *
     * @param non-empty-string $name Test name
     * @param non-empty-string $output Standard error content
     * @return non-empty-string
     */
    public static function testStdErr(string $name, string $output): string
    {
        return self::formatMessage('testStdErr', [
            'name' => $name,
            'out' => $output,
        ]);
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
     * Generates location hint for a test case from reflection.
     *
     * Format: php_qn://path/to/file.php::\ClassName
     *
     * @param \ReflectionClass<object> $reflection
     * @return non-empty-string|null
     */
    private static function caseLocationHint(\ReflectionClass $reflection): ?string
    {
        $file = $reflection->getFileName();
        $className = $reflection->getName();

        return $file !== false
            ? \sprintf('php_qn://%s::\\%s', $file, $className)
            : null;
    }

    /**
     * Generates location hint for a test method/function from reflection.
     *
     * Format: php_qn://path/to/file.php::\ClassName::methodName (for methods)
     * Format: php_qn://path/to/file.php::functionName (for functions)
     * Format: php_qn://path/to/file.php::\ClassName::methodName with data set #0 (with suffix)
     *
     * @param non-empty-string|null $suffix Optional suffix to append (e.g., " with data set #0")
     * @return non-empty-string|null
     */
    private static function testLocationHint(\ReflectionFunctionAbstract $reflection, ?string $suffix = null): ?string
    {
        $file = $reflection->getFileName();

        if ($file === false) {
            return null;
        }

        $name = $reflection->getName();

        // For methods, include class name
        if ($reflection instanceof \ReflectionMethod) {
            $className = $reflection->getDeclaringClass()->getName();
            $locationHint = \sprintf('php_qn://%s::\\%s::%s', $file, $className, $name);
        } else {
            // For functions, just the function name
            $locationHint = \sprintf('php_qn://%s::%s', $file, "\\$name");
        }

        return $suffix !== null ? $locationHint . $suffix : $locationHint;
    }
}
