<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

/**
 * Finds the lines a `codeCoverageIgnore` annotation leaves out of coverage, read the way php-code-coverage
 * reads it. The annotations are named here without their `@`, which would make this docblock ignore the class.
 *
 * A comment that holds nothing but the annotation ignores its own line, and a pair of comments holding its
 * `Start` and `End` forms ignores every line between them, inclusive. A docblock mentioning the annotation
 * anywhere ignores the class, trait, enum, function or method it documents.
 *
 * @internal
 */
final class IgnoredLines
{
    /** Characters php-code-coverage trims off a comment before comparing it to an annotation. */
    private const TRIM = "/ \n\r\t\0\x0B";

    private const DECLARATION = [\T_FUNCTION, \T_CLASS, \T_TRAIT, \T_INTERFACE, \T_ENUM];
    private const MODIFIERS = [\T_ABSTRACT, \T_FINAL, \T_READONLY, \T_PUBLIC, \T_PROTECTED, \T_PRIVATE, \T_STATIC];

    /** `{$` and `${` in a string open a brace too. */
    private const BRACE = ['{', \T_CURLY_OPEN, \T_DOLLAR_OPEN_CURLY_BRACES];

    private const BRACKET = ['[', \T_ATTRIBUTE];

    /**
     * @param list<\PhpToken> $tokens
     * @return array<int<1, max>, true> Line number => true.
     */
    public static function of(array $tokens): array
    {
        $ignored = [];
        $start = null;
        foreach ($tokens as $i => $t) {
            if (!$t->is([\T_COMMENT, \T_DOC_COMMENT])) {
                continue;
            }

            $annotation = \trim($t->text, self::TRIM);
            if ($annotation === '@codeCoverageIgnore') {
                $ignored[$t->line] = true;
            } elseif ($annotation === '@codeCoverageIgnoreStart') {
                $start = $t->line;
            } elseif ($annotation === '@codeCoverageIgnoreEnd') {
                $ignored += self::range($start ?? $t->line, $t->line);
                $start = null;
            }

            if ($t->id === \T_DOC_COMMENT && \str_contains($t->text, '@codeCoverageIgnore')) {
                $lines = self::declaration($tokens, $i);
                $lines === null or $ignored += self::range(...$lines);
            }
        }

        \ksort($ignored);

        return $ignored;
    }

    /**
     * First and last line of the declaration a docblock documents, when it documents a class-like or a
     * named function.
     *
     * @param list<\PhpToken> $tokens
     * @return array{int, int}|null
     */
    private static function declaration(array $tokens, int $doc): ?array
    {
        # Attributes and modifiers belong to the declaration.
        $i = self::next($tokens, $doc);
        $first = $i === null ? null : $tokens[$i]->line;
        while ($i !== null && $tokens[$i]->is([\T_ATTRIBUTE, ...self::MODIFIERS])) {
            $i = self::next($tokens, $tokens[$i]->id === \T_ATTRIBUTE ? self::closing($tokens, $i, self::BRACKET, ']') : $i);
        }
        if ($i === null || !$tokens[$i]->is(self::DECLARATION)) {
            return null;
        }

        # A closure or an anonymous class has no name.
        $name = self::next($tokens, $i);
        $name !== null && $tokens[$name]->text === '&' and $name = self::next($tokens, $name);
        if ($name === null || \in_array($tokens[$name]->text, ['(', '{'], true)) {
            return null;
        }

        # The body opens past the parameter list; a body-less method ends at its `;`.
        $depth = 0;
        for ($j = $name; isset($tokens[$j]); $j++) {
            $text = $tokens[$j]->text;
            $text === '(' and $depth++;
            $text === ')' and $depth--;
            if ($depth === 0 && $text === ';') {
                return [$first, $tokens[$j]->line];
            }
            if ($depth === 0 && $tokens[$j]->is(self::BRACE)) {
                $close = self::closing($tokens, $j, self::BRACE, '}');
                return [$first, ($tokens[$close] ?? \end($tokens))->line];
            }
        }

        return null;
    }

    /**
     * Index of the token closing the bracket opened at `$open`, or past the end when it never closes.
     *
     * @param list<\PhpToken> $tokens
     * @param list<int|string> $openers
     */
    private static function closing(array $tokens, int $open, array $openers, string $closer): int
    {
        $depth = 0;
        for ($i = $open; isset($tokens[$i]); $i++) {
            if ($tokens[$i]->is($openers)) {
                $depth++;
            } elseif ($tokens[$i]->text === $closer && --$depth === 0) {
                return $i;
            }
        }

        return $i;
    }

    /**
     * Index of the next token that is not whitespace or a comment.
     *
     * @param list<\PhpToken> $tokens
     */
    private static function next(array $tokens, int $i): ?int
    {
        while (isset($tokens[++$i])) {
            if (!$tokens[$i]->isIgnorable()) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return array<int, true>
     */
    private static function range(int $from, int $to): array
    {
        return \array_fill_keys(\range($from, $to), true);
    }
}
