<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\Internal\Assertion\AssertString as AssertStringImpl;
use Testo\Assert\Internal\StringNormalizer;
use Testo\Assert\Internal\Support;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::string()
 */
#[Test]
#[Covers(AssertStringImpl::class)]
#[Covers(StringNormalizer::class)]
#[Covers(Support::class, 'escapeControlChars')]
final class AssertStringModifiers
{
    public function ignoringCaseOnEachCheck(): void
    {
        Assert::string("Hello World")
            ->ignoringCase()
            ->contains('o WOR')
            ->notContains('bye')
            ->startsWith('hELLO')
            ->endsWith('WORLD');
    }

    public function ignoringLineEndingsOnEachCheck(): void
    {
        Assert::string("first\r\nsecond\rthird\n")
            ->ignoringLineEndings()
            ->contains("first\nsecond\nthird")
            ->notContains('fourth')
            ->startsWith("first\n")
            ->endsWith("third\n");
    }

    public function ignoringLineEndingsNormalizesTheArgument(): void
    {
        Assert::string("a\nb\n")
            ->ignoringLineEndings()
            ->contains("a\r\nb")
            ->startsWith("a\rb")
            ->endsWith("b\r\n");
    }

    public function combinedModifiers(): void
    {
        Assert::string("Build\r\nDONE\r\n")
            ->ignoringCase()
            ->ignoringLineEndings()
            ->contains("build\ndone")
            ->notContains('error')
            ->startsWith("BUILD\n")
            ->endsWith("done\n");

        Assert::string("Build\r\nDONE\r\n")
            ->ignoringLineEndings()
            ->ignoringCase()
            ->endsWith("done\r\n");
    }

    public function multibyteCase(): void
    {
        Assert::string("ПРИВЕТ, Мир")
            ->ignoringCase()
            ->startsWith('привет')
            ->endsWith('МИР')
            ->contains('т, м');
        Assert::string("ΣΟΦΙΑ Ärger")->ignoringCase()->startsWith('σοφια')->endsWith('ärger');
    }

    public function ignoringCaseDoesNotExpandCharacters(): void
    {
        Assert::string("Straße")->ignoringCase()->notContains('STRASSE')->contains('STRAßE');
        Assert::string("STRASSE")->ignoringCase()->notContains('Straße');
    }

    public function emptyArgumentPassesWithModifiers(): void
    {
        Assert::string("Text")->ignoringCase()->ignoringWhitespace()->ignoringAnsi()->contains('')->startsWith('')->endsWith('');
    }

    public function modifiersLeaveTheOriginalStrict(): void
    {
        $string = Assert::string("Hello\r\n  World");

        $string->ignoringCase()->contains('HELLO');
        $string->ignoringLineEndings()->contains("Hello\n  World");
        $string->ignoringWhitespace()->contains("Hello\nWorld");
        $string->notContains('HELLO')->notContains("Hello\n  World")->notContains("Hello\nWorld");
    }

    public function ignoringLineEndingsNormalizesOnlyTheRegexSubject(): void
    {
        Assert::string("foo\r\nbar")->notMatchesRegex('/^foo$/m');
        Assert::string("foo\r\nbar")->ignoringLineEndings()->matchesRegex('/^foo$/m')->notMatchesRegex('/\r/');
        Assert::string("foo\nbar")->ignoringLineEndings()->notMatchesRegex("/foo\r\nbar/");
    }

    public function ignoringCaseDoesNotAffectRegexChecks(): void
    {
        Assert::string("HELLO")
            ->ignoringCase()
            ->matchesRegex('/^HELLO$/')
            ->notMatchesRegex('/hello/')
            ->matchesRegex('/hello/i');
    }

    public function ignoringWhitespaceTrimsAndCollapsesEachLine(): void
    {
        Assert::string("  Hello \t  World  \r\n\t second   line ")
            ->ignoringWhitespace()
            ->contains("Hello World\nsecond line")
            ->startsWith('Hello World')
            ->endsWith('second line')
            ->notMatchesRegex('/  /');
    }

    public function ignoringWhitespaceNormalizesTheArgumentLineByLine(): void
    {
        Assert::string("<li>\n<b>Total:</b> 5\n</li>")
            ->ignoringWhitespace()
            ->contains("  <li>\r\n    <b>Total:</b>   5 \n  </li>  ");
    }

    public function ignoringWhitespaceEmptiesWhitespaceOnlyLines(): void
    {
        Assert::string("a\n \t \nb")->ignoringWhitespace()->contains("a\n\nb");
    }

    public function ignoringWhitespaceCoversUnicodeSpaces(): void
    {
        Assert::string("price:\u{00A0}\u{00A0}10\u{2003}EUR\u{3000}")
            ->ignoringWhitespace()
            ->contains('price: 10 EUR')
            ->endsWith('EUR');
    }

    public function ignoringWhitespaceFallsBackToAsciiOnInvalidUtf8(): void
    {
        Assert::string("\xFF  a \t b ")
            ->ignoringWhitespace()
            ->contains('a b')
            ->startsWith("\xFF a")
            ->endsWith('a b');
    }

    public function ignoringWhitespaceAcrossLineBreaks(): void
    {
        Assert::string("SELECT id,\n  name\r\nFROM users\n\n")
            ->ignoringWhitespace(lineBreaks: true)
            ->startsWith("SELECT id, name\nFROM")
            ->contains('id, name FROM')
            ->endsWith('users')
            ->matchesRegex('/^SELECT.*users$/')
            ->notMatchesRegex('/^FROM/m');
    }

    public function ignoringWhitespaceLastCallWins(): void
    {
        Assert::string("a \n b")
            ->ignoringWhitespace(lineBreaks: true)
            ->ignoringWhitespace()
            ->contains("a\nb")
            ->notContains('a b');

        Assert::string("a \n b")
            ->ignoringWhitespace()
            ->ignoringWhitespace(lineBreaks: true)
            ->contains('a b');
    }

    public function ignoringWhitespaceRegexChecksSeeLines(): void
    {
        Assert::string("  foo  \n\tbar ")
            ->ignoringWhitespace()
            ->matchesRegex('/^foo$/m')
            ->matchesRegex('/^bar$/m')
            ->notMatchesRegex('/\s$/m');
    }

    public function wordBoundaryNeedsARegex(): void
    {
        Assert::string("seafood")->ignoringWhitespace()->contains(' food ')->notMatchesRegex('/\bfood\b/');
    }

    public function ignoringBlankLinesRemovesEmptyAndWhitespaceOnlyLines(): void
    {
        Assert::string("\n\r\nStep 1\n   \n\t\nStep 2\n\n")
            ->ignoringBlankLines()
            ->contains("Step 1\nStep 2")
            ->startsWith('Step 1')
            ->endsWith('Step 2')
            ->notMatchesRegex('/\n\n/');
    }

    public function ignoringBlankLinesKeepsIndentation(): void
    {
        Assert::string("  a\n\n  b")
            ->ignoringBlankLines()
            ->startsWith("  a\n  b")
            ->notContains("a\nb");
    }

    public function ignoringBlankLinesWithWhitespaceKeepsLineDivision(): void
    {
        Assert::string("  a  \n\n   b  \n")
            ->ignoringWhitespace()
            ->ignoringBlankLines()
            ->contains("a\nb")
            ->endsWith("a\nb");
    }

    public function normalizationOrderDoesNotDependOnCallOrder(): void
    {
        $text = "\n  One\u{00A0} two \n\n THREE ";

        Assert::string($text)->ignoringBlankLines()->ignoringWhitespace()->ignoringCase()->contains("one two\nthree");
        Assert::string($text)->ignoringCase()->ignoringWhitespace()->ignoringBlankLines()->contains("one two\nthree");

        Assert::string("\e[32m  OK \e[0m")->ignoringWhitespace()->ignoringAnsi()->startsWith('OK')->endsWith('OK');
    }

    public function ignoringAnsiStripsStyles(): void
    {
        Assert::string("\e[1;32mSuccess\e[0m: \e[1mall\e[22m done\e[m")
            ->ignoringAnsi()
            ->contains('Success: all done')
            ->startsWith('Success')
            ->endsWith('done');
    }

    public function ignoringAnsiStripsCursorAndEraseSequences(): void
    {
        Assert::string("\e[2K\e[1G\e[?25lline\e[3A\e[?25h")->ignoringAnsi()->startsWith('line')->endsWith('line');
    }

    public function ignoringAnsiKeepsHyperlinkText(): void
    {
        Assert::string("see \e]8;;https://php-testo.github.io\x07docs\e]8;;\x07 and \e]8;;https://x.test\e\\site\e]8;;\e\\")
            ->ignoringAnsi()
            ->contains('see docs and site')
            ->notContains('https');
    }

    public function ignoringAnsiStripsCharsetAndTwoByteEscapes(): void
    {
        Assert::string("\e7\e(Btext\e(0\eMmore\e8\ec")
            ->ignoringAnsi()
            ->startsWith('textmore')
            ->endsWith('textmore');
    }

    public function ignoringAnsiLeavesUtf8Alone(): void
    {
        Assert::string("Věc \e[31mčeská\e[0m")->ignoringAnsi()->contains('Věc česká');
    }

    public function ignoringAnsiWithWhitespace(): void
    {
        Assert::string("\e[32m  OK \e[0m")
            ->ignoringAnsi()
            ->ignoringWhitespace()
            ->contains('OK')
            ->startsWith('OK')
            ->endsWith('OK');
    }

    public function ignoringAnsiNormalizesTheArgument(): void
    {
        Assert::string("status: OK")->ignoringAnsi()->endsWith("\e[32mOK\e[0m");
    }

    public function ignoringAnsiStripsTheRegexSubject(): void
    {
        Assert::string("\e[32mOK\e[0m")->ignoringAnsi()->matchesRegex('/^OK$/')->notMatchesRegex('/\e/');
    }

    /**
     * @param list<non-empty-string> $modifiers
     * @param non-empty-string $check
     */
    #[DataSet(['Hello World', ['ignoringCase'], 'contains', 'Error', 'contains "Error" (ignoring case)'], 'contains, case')]
    #[DataSet(['Hello World', ['ignoringCase'], 'notContains', 'WORLD', 'does not contain "WORLD" (ignoring case)'], 'notContains, case')]
    #[DataSet(['Hello World', ['ignoringCase'], 'startsWith', 'WORLD', 'starts with "WORLD" (ignoring case)'], 'startsWith, case')]
    #[DataSet(['Hello World', ['ignoringCase'], 'endsWith', 'HELLO', 'ends with "HELLO" (ignoring case)'], 'endsWith, case')]
    #[DataSet(["a\r\nb", ['ignoringLineEndings'], 'contains', "A\nb", "contains \"A\nb\" (ignoring line endings)"], 'contains, line endings')]
    #[DataSet(["a\r\nb", ['ignoringLineEndings'], 'notContains', "a\nb", "does not contain \"a\nb\" (ignoring line endings)"], 'notContains, line endings')]
    #[DataSet(["a\r\nb", ['ignoringLineEndings'], 'startsWith', "\n", "starts with \"\n\" (ignoring line endings)"], 'startsWith, line endings')]
    #[DataSet(["a\r\nb", ['ignoringLineEndings'], 'endsWith', "a\n", "ends with \"a\n\" (ignoring line endings)"], 'endsWith, line endings')]
    #[DataSet(["Done\r\n", ['ignoringCase', 'ignoringLineEndings'], 'endsWith', "fail\n", "ends with \"fail\n\" (ignoring line endings, ignoring case)"], 'both modifiers')]
    #[DataSet(["foo\r\nbar", ['ignoringLineEndings', 'ignoringCase'], 'notMatchesRegex', '/^foo$/m', 'does not match regex /^foo$/m (ignoring line endings):'], 'regex, line endings')]
    #[DataSet(['HELLO', ['ignoringCase'], 'matchesRegex', '/hello/', 'matches regex /hello/: the regex does not match'], 'regex, case')]
    #[DataSet(["a  b", ['ignoringWhitespace'], 'contains', 'a c', 'contains "a c" (ignoring whitespace)'], 'contains, whitespace')]
    #[DataSet(["a\nb", ['ignoringWhitespace'], 'contains', 'a b', 'contains "a b" (ignoring whitespace)'], 'whitespace keeps line breaks')]
    #[DataSet(["a\nb", ['ignoringWhitespace'], 'notMatchesRegex', '/^b/m', 'does not match regex /^b/m (ignoring whitespace)'], 'regex, whitespace')]
    #[DataSet(["a\n\nb", ['ignoringBlankLines'], 'startsWith', 'b', 'starts with "b" (ignoring blank lines)'], 'startsWith, blank lines')]
    #[DataSet(["\e[1mOK\e[0m", ['ignoringAnsi'], 'endsWith', 'KO', 'ends with "KO" (ignoring ANSI codes)'], 'endsWith, ansi')]
    #[DataSet(["a\n b", ['ignoringBlankLines', 'ignoringAnsi', 'ignoringWhitespace', 'ignoringCase', 'ignoringLineEndings'], 'contains', 'x', 'contains "x" (ignoring ANSI codes, ignoring line endings, ignoring whitespace, ignoring blank lines, ignoring case)'], 'every modifier')]
    public function modifiedCheckFails(string $value, array $modifiers, string $check, string $argument, string $description): never
    {
        $string = Assert::string($value);
        foreach ($modifiers as $modifier) {
            $string = $string->{$modifier}();
        }

        Expect::exception(AssertionException::class)->withMessageContaining($description);
        $string->{$check}($argument);
    }

    public function acrossLineBreaksLabel(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('contains "a  c" (ignoring whitespace across line breaks)');
        Assert::string("a\nb")->ignoringWhitespace(lineBreaks: true)->contains('a  c');
    }

    /**
     * @param list<non-empty-string> $modifiers
     * @param non-empty-string $check
     */
    #[DataSet(['text', ['ignoringWhitespace'], 'contains', "   ", 'The argument "   " of contains() is empty after normalization (ignoring whitespace).'], 'spaces, whitespace')]
    #[DataSet(['text', ['ignoringWhitespace'], 'notContains', "\t \u{00A0}", 'of notContains() is empty after normalization'], 'tab and no-break space, whitespace')]
    #[DataSet(['text', ['ignoringWhitespace'], 'contains', "\n \n", 'of contains() is empty after normalization (ignoring whitespace across line breaks)', true], 'line breaks, whitespace across line breaks')]
    #[DataSet(['text', ['ignoringBlankLines'], 'startsWith', "\n \n", 'of startsWith() is empty after normalization (ignoring blank lines)'], 'blank lines')]
    #[DataSet(['text', ['ignoringAnsi'], 'endsWith', "\e[0m", 'The argument "\e[0m" of endsWith() is empty after normalization (ignoring ANSI codes).'], 'ansi')]
    public function argumentEmptyAfterNormalizationIsRejected(
        string $value,
        array $modifiers,
        string $check,
        string $argument,
        string $message,
        bool $lineBreaks = false,
    ): never {
        $string = Assert::string($value);
        foreach ($modifiers as $modifier) {
            $string = $modifier === 'ignoringWhitespace' ? $string->ignoringWhitespace($lineBreaks) : $string->{$modifier}();
        }

        Expect::exception(\InvalidArgumentException::class)->withMessageContaining($message);
        $string->{$check}($argument);
    }

    public function failureShowsTheOriginalValue(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('ПРИВЕТ World')
            ->withMessageContaining('contains "Bye" (ignoring case)')
            ->withMessageContaining('my wonderful message');
        Assert::string("ПРИВЕТ World")->ignoringCase()->contains('Bye', 'my wonderful message');
    }

    public function failureMessageSpellsOutEscapeCodes(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('`"\e[31mFAIL\e[0m"`')
            ->withMessageContaining('contains "\e[1mOK" (ignoring ANSI codes)')
            ->withMessageMatchingRegex('/\A[^\x1B]*\z/');
        Assert::string("\e[31mFAIL\e[0m")->ignoringAnsi()->contains("\e[1mOK");
    }
}
