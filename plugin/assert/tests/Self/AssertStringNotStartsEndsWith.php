<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\Internal\Assertion\AssertString as AssertStringImpl;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::string()
 */
#[Test]
#[Covers(AssertStringImpl::class, 'notStartsWith')]
#[Covers(AssertStringImpl::class, 'notEndsWith')]
final class AssertStringNotStartsEndsWith
{
    public function withoutModifiers(): void
    {
        Assert::string("report.json")->notStartsWith('json')->notEndsWith('report')->notStartsWith('Report');
    }

    public function withModifiers(): void
    {
        Assert::string("Hello World")->ignoringCase()->notStartsWith('world')->notEndsWith('hello');
        Assert::string("a\r\nb")->ignoringLineEndings()->notStartsWith("a\r\r")->notEndsWith("\r\r");
        Assert::string("  a  b  ")->ignoringWhitespace()->notStartsWith('b')->notEndsWith('a');
        Assert::string("\e[1mOK\e[0m")->ignoringAnsi()->notStartsWith("\e")->notEndsWith('m');
    }

    public function emptyArgumentAlwaysFails(): never
    {
        Expect::exception(AssertionException::class)->withMessageContaining('does not start with ""');
        Assert::string("text")->notStartsWith('');
    }

    /**
     * @param non-empty-string $check
     * @param list<non-empty-string> $modifiers
     */
    #[DataSet(['Hello', [], 'notStartsWith', 'He', 'does not start with "He": the string starts with it'], 'plain prefix')]
    #[DataSet(['Hello', [], 'notEndsWith', 'lo', 'does not end with "lo": the string ends with it'], 'plain suffix')]
    #[DataSet(['Hello', ['ignoringCase'], 'notStartsWith', 'HE', 'does not start with "HE" (ignoring case)'], 'prefix, case')]
    #[DataSet(["a\r\n", ['ignoringLineEndings'], 'notEndsWith', "\n", "does not end with \"\n\" (ignoring line endings)"], 'suffix, line endings')]
    #[DataSet(["\e[32m  OK ", ['ignoringAnsi', 'ignoringWhitespace'], 'notStartsWith', 'OK', 'does not start with "OK" (ignoring ANSI codes, ignoring whitespace)'], 'prefix, ansi and whitespace')]
    public function fails(string $value, array $modifiers, string $check, string $argument, string $description): never
    {
        $string = Assert::string($value);
        foreach ($modifiers as $modifier) {
            $string = $string->{$modifier}();
        }

        Expect::exception(AssertionException::class)->withMessageContaining($description);
        $string->{$check}($argument);
    }

    /**
     * @param non-empty-string $check
     */
    #[DataSet(['notStartsWith', '   '])]
    #[DataSet(['notEndsWith', "\t"])]
    public function argumentEmptyAfterNormalizationIsRejected(string $check, string $argument): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessageContaining("of {$check}() is empty after normalization (ignoring whitespace)");
        Assert::string("text")->ignoringWhitespace()->{$check}($argument);
    }
}
