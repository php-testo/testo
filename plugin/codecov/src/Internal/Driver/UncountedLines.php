<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal\Driver;

use Testo\Codecov\Internal\ExecutableLines;
use Testo\Codecov\Internal\IgnoredLines;

/**
 * Drops coverage entries on lines that hold no code or that a `codeCoverageIgnore` annotation leaves out.
 *
 * A driver attaches a line to an opcode, and an opcode always starts on a token, except the implicit
 * `return` of a file's top-level code: Xdebug and PCOV report it on the file's last line, unexecuted
 * whenever the file was loaded outside a coverage window. Past a final newline that line is blank; without
 * one it holds what closes the last statement, such as `}` or `);`, or a `?>`.
 *
 * A branch lying wholly on ignored lines is dropped too, with every path through it and every exit into
 * it, so a decision left with a single exit is no longer a partly covered one.
 *
 * @internal
 */
final class UncountedLines
{
    /** Punctuation that only ends what earlier lines opened. */
    private const CLOSERS = ['}', ')', ']', ';', ','];

    /**
     * Lines that count and lines ignored, by file path, or false for a file that cannot be read. Filled on
     * first sight: `remove()` runs on every coverage collection.
     *
     * @var array<string, array{array<int, true>, array<int, true>}|false>
     */
    private static array $lines = [];

    /**
     * @template T of array
     * @param array<string, T> $data Raw coverage keyed by file path, line-only or with a `lines` key.
     * @return array<string, T>
     */
    public static function remove(array $data): array
    {
        foreach ($data as $path => $entry) {
            $scan = self::$lines[$path] ??= self::scan($path);
            if ($scan === false) {
                continue;
            }

            [$counted, $ignored] = $scan;
            if (!\array_key_exists('lines', $entry)) {
                $data[$path] = \array_intersect_key($entry, $counted);
                continue;
            }

            $entry['lines'] = \array_intersect_key($entry['lines'], $counted);
            $ignored === [] || !isset($entry['functions'])
                or $entry['functions'] = self::withoutBranchesOn($entry['functions'], $ignored);
            $data[$path] = $entry;
        }

        return $data;
    }

    /**
     * @return array{array<int, true>, array<int, true>}|false Lines that count and lines ignored.
     */
    private static function scan(string $path): array|false
    {
        $source = @\file_get_contents($path);
        if ($source === false) {
            return false;
        }

        $tokens = \PhpToken::tokenize($source);
        $end = \end($tokens);
        # PHP counts a line break of any style, and the last token may span several.
        $last = $end === false ? 1 : $end->line + \preg_match_all('/\r\n|\r|\n/', $end->text);
        $code = [];
        $onlyClosers = true;
        foreach ($tokens as $t) {
            if ($t->isIgnorable() || $t->is([\T_OPEN_TAG, \T_CLOSE_TAG])) {
                continue;
            }
            # Whitespace left after a closing tag is echoed, but it is not code to cover.
            if ($t->id === \T_INLINE_HTML && \trim($t->text) === '') {
                continue;
            }

            $code[$t->line] = true;
            $t->line === $last && !\in_array($t->text, self::CLOSERS, true) and $onlyClosers = false;
        }

        # A last line that only closes, such as `}` or `);`, carries the top-level `return`, and code only when
        # it ends a function body that falls through.
        if (isset($code[$last]) && $onlyClosers && !isset(ExecutableLines::of($source)[$last])) {
            unset($code[$last]);
        }

        $ignored = IgnoredLines::of($tokens);

        return [\array_diff_key($code, $ignored), $ignored];
    }

    /**
     * @param array<array-key, array{branches: array<int, array{line_start: int, line_end: int, out: list<int>, out_hit: list<int>}>, paths: array<array-key, array{path: list<int>}>}> $functions
     * @param array<int, true> $lines
     * @return array<array-key, array{branches: array<int, array{line_start: int, line_end: int, out: list<int>, out_hit: list<int>}>, paths: array<array-key, array{path: list<int>}>}>
     */
    private static function withoutBranchesOn(array $functions, array $lines): array
    {
        foreach ($functions as $name => $function) {
            $dropped = [];
            foreach ($function['branches'] as $id => $branch) {
                self::confined($branch['line_start'], $branch['line_end'], $lines) and $dropped[$id] = true;
            }
            if ($dropped === []) {
                continue;
            }

            $function['branches'] = \array_diff_key($function['branches'], $dropped);
            foreach ($function['branches'] as $id => $branch) {
                $kept = \array_filter($branch['out'], static fn(int $to): bool => !isset($dropped[$to]));
                $function['branches'][$id]['out'] = \array_values($kept);
                $function['branches'][$id]['out_hit'] = \array_values(\array_intersect_key($branch['out_hit'], $kept));
            }
            foreach ($function['paths'] as $key => $path) {
                if (\array_intersect_key(\array_flip($path['path']), $dropped) !== []) {
                    unset($function['paths'][$key]);
                }
            }
            $functions[$name] = $function;
        }

        return $functions;
    }

    /**
     * Whether every line of a branch is among the given ones. A loop's back edge runs from a later line to
     * an earlier one.
     *
     * @param array<int, true> $lines
     */
    private static function confined(int $start, int $end, array $lines): bool
    {
        for ($line = \min($start, $end); $line <= \max($start, $end); $line++) {
            if (!isset($lines[$line])) {
                return false;
            }
        }

        return true;
    }
}
