<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\Internal\Assertion\AssertString as AssertStringImpl;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::string()
 */
#[Test]
#[Covers(AssertStringImpl::class, 'same')]
#[Covers(AssertStringImpl::class, 'notSame')]
final class AssertStringSame
{
    public function sameWithoutModifiers(): void
    {
        Assert::string("Hello")->same('Hello')->notSame('hello')->notSame('Hello ');
        Assert::string("")->same('')->notSame(' ');
    }

    public function numericStringsAreNeverJuggled(): void
    {
        Assert::string("1e3")->notSame('1000')->same('1e3');
        Assert::string("0")->notSame('0.0')->notSame('');
        Assert::string("10")->ignoringWhitespace()->notSame('1e1');
    }

    public function sameWithEachModifier(): void
    {
        Assert::string("Hello World")->ignoringCase()->same('HELLO world')->notSame('hello');
        Assert::string("a\r\nb\r")->ignoringLineEndings()->same("a\nb\n")->same("a\rb\r\n");
        Assert::string("  a \t b \n c  ")->ignoringWhitespace()->same("a b\nc")->notSame('a b c');
        Assert::string("a\n b \nc")->ignoringWhitespace(lineBreaks: true)->same('a b c');
        Assert::string("\na\n  \nb\n\n")->ignoringBlankLines()->same("a\nb");
        Assert::string("\e[32mOK\e[0m")->ignoringAnsi()->same('OK');
    }

    public function sameWithCombinedModifiers(): void
    {
        Assert::string("\e[1m  DONE \e[0m\r\n\r\n  next ")
            ->ignoringAnsi()
            ->ignoringCase()
            ->ignoringBlankLines()
            ->ignoringWhitespace()
            ->same("done\nNEXT");
    }

    public function emptyExpectedIsAllowed(): void
    {
        Assert::string(" \t\n ")->ignoringWhitespace(lineBreaks: true)->same('   ');
        Assert::string("\n\n")->ignoringBlankLines()->same('');
        Assert::string("\e[0m")->ignoringAnsi()->same('')->notSame('x');
    }

    public function sameFailureDiffsTheNormalizedStrings(): void
    {
        try {
            Assert::string("Hello\r\nWORLD")->ignoringCase()->ignoringLineEndings()->same("hello\nthere", 'greeting');
        } catch (ComparisonFailure $failure) {
        }

        Assert::true(isset($failure));
        Assert::same($failure->expected, "hello\nthere");
        Assert::same($failure->actual, "hello\nworld");
        Assert::string($failure->getMessage())
            ->contains("is the same as \"hello\nthere\" (ignoring line endings, ignoring case)")
            ->contains("\"Hello\r\nWORLD\"")
            ->contains('greeting');
    }

    public function sameFailureWithoutModifiers(): never
    {
        Expect::exception(ComparisonFailure::class)
            ->withMessageContaining('is the same as "1000": expected "1000", got "1e3"');
        Assert::string("1e3")->same('1000');
    }

    /**
     * @param non-empty-string $modifier
     */
    #[DataSet(['Hello', 'ignoringCase', 'HELLO', 'is not the same as "HELLO" (ignoring case): the strings are the same'])]
    #[DataSet(["a\r\n", 'ignoringLineEndings', "a\n", "is not the same as \"a\n\" (ignoring line endings)"])]
    #[DataSet(['a  b', 'ignoringWhitespace', ' a b ', 'is not the same as " a b " (ignoring whitespace)'])]
    #[DataSet(["\e[1mX", 'ignoringAnsi', 'X', 'is not the same as "X" (ignoring ANSI codes)'])]
    public function notSameFails(string $value, string $modifier, string $expected, string $description): never
    {
        Expect::exception(AssertionException::class)->withMessageContaining($description);
        Assert::string($value)->{$modifier}()->notSame($expected);
    }

    public function notSameFailureIsNotAComparison(): void
    {
        try {
            Assert::string("x")->notSame('x');
        } catch (AssertionException $failure) {
        }

        Assert::true(isset($failure));
        Assert::false($failure instanceof ComparisonFailure);
    }
}
