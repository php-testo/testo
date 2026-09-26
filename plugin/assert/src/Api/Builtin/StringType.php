<?php

declare(strict_types=1);

namespace Testo\Assert\Api\Builtin;

use Testo\Assert\State\Assertion\AssertionException;

/**
 * Assertion utilities for string data type.
 *
 * The `ignoring*()` modifiers return a new instance whose checks compare normalized text; the
 * instance they are called on stays strict, and a mode cannot be switched off again. The string
 * and every check argument are normalized by the same rules, always from the original value and in
 * a fixed order whatever the call order: ANSI codes, line endings, whitespace, blank lines, case.
 * A substring, prefix or suffix that is not empty but normalizes to an empty string throws
 * {@see \InvalidArgumentException}, since it would match any string. Failure messages show the
 * original string and argument followed by the active modes, e.g. `contains "Error" (ignoring case)`.
 *
 * The patterns run against the string normalized by every mode except {@see self::ignoringCase()};
 * the pattern itself is never changed.
 *
 * @note This interface is not intended to be implemented by userland code.
 *       New methods may be added in minor versions without a major version bump.
 */
interface StringType
{
    /**
     * Compare case-insensitively: the string and each check argument are lowercased with
     * `mb_strtolower()`, so `ПРИВЕТ` matches `привет` while `STRASSE` does not match `Straße`.
     *
     * Does not apply to the patterns: use the `i` flag there.
     *
     * ```php
     * Assert::string($html)->ignoringCase()->contains('<title>');
     * ```
     */
    public function ignoringCase(): static;

    /**
     * Treat `\r\n`, `\r` and `\n` as the same line ending: `\r\n` and a lone `\r` become `\n`.
     *
     * ```php
     * Assert::string($output)->ignoringLineEndings()->endsWith("done\n");
     * ```
     */
    public function ignoringLineEndings(): static;

    /**
     * Ignore the amount of whitespace. Line endings are normalized as by {@see self::ignoringLineEndings()},
     * then every line is trimmed and each run of horizontal whitespace (space, tab, no-break space and
     * the other Unicode spaces) becomes one space. Line breaks stay; a whitespace-only line becomes empty.
     *
     * With `$lineBreaks`, every run of whitespace including line breaks becomes one space and the whole
     * text is trimmed, so the result is a single line — also for the patterns.
     *
     * Invalid UTF-8 is normalized with the ASCII whitespace only. A second call replaces the first.
     * The edges of an argument are trimmed too, so `contains(" foo ")` no longer requires word
     * boundaries: use `matchesPattern('/\bfoo\b/')` for that.
     *
     * ```php
     * Assert::string($html)->ignoringWhitespace()->contains("<li>\n<b>Total:</b> 5\n</li>");
     * Assert::string($sql)->ignoringWhitespace(lineBreaks: true)->startsWith('SELECT id, name FROM users');
     * ```
     *
     * @param bool $lineBreaks Whether line breaks count as whitespace too.
     */
    public function ignoringWhitespace(bool $lineBreaks = false): static;

    /**
     * Ignore blank lines: line endings are normalized to `\n` and every empty or whitespace-only line
     * is removed, so runs of line breaks collapse and leading and trailing blank lines disappear.
     * The remaining lines keep their indentation unless {@see self::ignoringWhitespace()} is active.
     *
     * ```php
     * Assert::string($output)->ignoringBlankLines()->contains("Step 1\nStep 2");
     * ```
     */
    public function ignoringBlankLines(): static;

    /**
     * Ignore terminal markup: 7-bit ANSI/VT escape sequences are removed — CSI (colors, cursor
     * movement, erasing), OSC ended by BEL or `ESC \` (the text of an OSC 8 hyperlink stays), charset
     * designation such as `ESC ( B` and the other two-byte escapes. The 8-bit C1 forms, carriage-return
     * overwrites and backspaces are left as they are.
     *
     * ```php
     * Assert::string($commandOutput)->ignoringAnsi()->contains('[OK] Cache cleared');
     * ```
     */
    public function ignoringAnsi(): static;

    /**
     * Asserts that the string is identical (`===`) to the expected one, both normalized by the active
     * modifiers. Without modifiers it behaves like `Assert::same()` for strings. Unlike
     * `Assert::equals()`, numeric strings are never compared as numbers: `"1e3"` is not `"1000"`.
     *
     * An expected string that normalizes to empty is a valid expectation here:
     * `ignoringWhitespace()->same('')` asserts a blank string.
     *
     * On failure the diff shows the normalized strings, the message the original ones.
     *
     * ```php
     * Assert::string($output)->ignoringLineEndings()->same("line 1\nline 2\n");
     * ```
     *
     * @param string $expected The expected string.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function same(string $expected, string $message = ''): static;

    /**
     * Asserts that the string differs from the given one, both normalized by the active modifiers.
     * Numeric strings are compared as strings, like in {@see self::same()}.
     *
     * ```php
     * Assert::string($name)->ignoringCase()->notSame('admin');
     * ```
     *
     * @param string $expected The string the value must differ from.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    public function notSame(string $expected, string $message = ''): static;

    /**
     * Asserts that the string contains the given substring.
     *
     * @param non-empty-string $needle Substring to search for.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     * @throws \InvalidArgumentException when the needle is empty only after normalization.
     */
    public function contains(string $needle, string $message = ''): static;

    /**
     * Asserts that the string does not contain the given substring.
     *
     * @param non-empty-string $needle Substring to search for.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     * @throws \InvalidArgumentException when the needle is empty only after normalization.
     */
    public function notContains(string $needle, string $message = ''): static;

    /**
     * Asserts that the string starts with the given prefix.
     *
     * @param non-empty-string $prefix Expected beginning of the string.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     * @throws \InvalidArgumentException when the prefix is empty only after normalization.
     */
    public function startsWith(string $prefix, string $message = ''): static;

    /**
     * Asserts that the string ends with the given suffix.
     *
     * @param non-empty-string $suffix Expected end of the string.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     * @throws \InvalidArgumentException when the suffix is empty only after normalization.
     */
    public function endsWith(string $suffix, string $message = ''): static;

    /**
     * Asserts that the string does not start with the given prefix.
     *
     * ```php
     * Assert::string($path)->ignoringCase()->notStartsWith('/tmp/');
     * ```
     *
     * @param non-empty-string $prefix Beginning the string must not have.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     * @throws \InvalidArgumentException when the prefix is empty only after normalization.
     */
    public function notStartsWith(string $prefix, string $message = ''): static;

    /**
     * Asserts that the string does not end with the given suffix.
     *
     * ```php
     * Assert::string($output)->ignoringWhitespace()->notEndsWith('ERROR');
     * ```
     *
     * @param non-empty-string $suffix End the string must not have.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     * @throws \InvalidArgumentException when the suffix is empty only after normalization.
     */
    public function notEndsWith(string $suffix, string $message = ''): static;

    /**
     * Asserts that the string matches the given PCRE pattern.
     *
     * The modifiers other than {@see self::ignoringCase()} normalize the string, never the pattern;
     * for case-insensitive matching use the `i` flag.
     *
     * @param string $pattern Full PCRE pattern with delimiters and flags, e.g. `/^\d+$/i`.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     * @throws \InvalidArgumentException when the pattern is invalid.
     */
    public function matchesPattern(string $pattern, string $message = ''): static;

    /**
     * Asserts that the string does not match the given PCRE pattern.
     *
     * The modifiers other than {@see self::ignoringCase()} normalize the string, never the pattern;
     * for case-insensitive matching use the `i` flag.
     *
     * @param string $pattern Full PCRE pattern with delimiters and flags, e.g. `/\s$/`.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     * @throws \InvalidArgumentException when the pattern is invalid.
     */
    public function notMatchesPattern(string $pattern, string $message = ''): static;
}
