<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal\Driver;

use Testo\Codecov\Internal\ExecutableLines;

/**
 * Drops coverage entries on lines that hold no code.
 *
 * A driver attaches a line to an opcode, and an opcode always starts on a token, except the implicit
 * `return` of a file's top-level code: Xdebug and PCOV report it on the file's last line, unexecuted
 * whenever the file was loaded outside a coverage window. Past a final newline that line is blank; without
 * one it holds what closes the last statement, such as `}` or `);`, or a `?>`.
 *
 * @internal
 */
final class UncountedLines
{
    /** Punctuation that only ends what earlier lines opened. */
    private const CLOSERS = ['}', ')', ']', ';', ','];

    /**
     * Lines that hold code, by file path, or false for a file that cannot be read. Filled on first sight:
     * `remove()` runs on every coverage collection.
     *
     * @var array<string, array<int, true>|false>
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
            $code = self::$lines[$path] ??= self::scan($path);
            if ($code === false) {
                continue;
            }

            if (\array_key_exists('lines', $entry)) {
                $entry['lines'] = \array_intersect_key($entry['lines'], $code);
            } else {
                $entry = \array_intersect_key($entry, $code);
            }

            $data[$path] = $entry;
        }

        return $data;
    }

    /**
     * @return array<int, true>|false
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

        return $code;
    }
}
