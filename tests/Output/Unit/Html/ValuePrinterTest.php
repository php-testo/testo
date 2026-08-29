<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Html;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Output\Html\Internal\ValuePrinter;
use Testo\Test;

/**
 * The short label a value gets in the report: scalars verbatim, strings quoted and cut at a limit,
 * arrays flattened to a shallow depth, and objects named by their class or their own readable form.
 */
#[Test]
#[Covers(ValuePrinter::class)]
final class ValuePrinterTest
{
    #[DataSet([null, 'null'], 'null is spelled out')]
    #[DataSet([true, 'true'], 'true is spelled out')]
    #[DataSet([false, 'false'], 'false is spelled out')]
    #[DataSet([42, '42'], 'a positive int')]
    #[DataSet([-7, '-7'], 'a negative int')]
    #[DataSet([0, '0'], 'zero')]
    #[DataSet([3.14, '3.14'], 'a float')]
    #[DataSet([1.5, '1.5'], 'another float')]
    #[DataSet(['hi', "'hi'"], 'a short string is quoted')]
    #[DataSet(['', "''"], 'an empty string is two quotes')]
    public function aScalarIsPrintedVerbatim(mixed $value, string $expected): void
    {
        Assert::same(ValuePrinter::print($value), $expected);
    }

    #[DataSet(["x\ny", "'x\\ny'"], 'a newline becomes a backslash-n')]
    #[DataSet(["x\ry", "'x\\ry'"], 'a carriage return becomes a backslash-r')]
    #[DataSet(["x\ty", "'x\\ty'"], 'a tab becomes a backslash-t')]
    #[DataSet(["a\\b", "'a\\\\b'"], 'a backslash is doubled')]
    #[DataSet(["a'b", "'a\\'b'"], 'a quote is escaped')]
    public function aControlCharacterIsEscapedTheWayTheSourceWroteIt(string $value, string $expected): void
    {
        // Escapes read as the test file wrote them, so a reader can compare the label against the source.
        Assert::same(ValuePrinter::print($value), $expected);
    }

    public function aStringAtTheLimitIsPrintedInFull(): void
    {
        $value = \str_repeat('a', 120);

        // 120 is the longest string rendered whole; the limit itself is not a cut.
        Assert::same(ValuePrinter::print($value), "'" . $value . "'");
    }

    public function aStringOverTheLimitIsCutAndMarked(): void
    {
        $value = \str_repeat('a', 121);

        // One past the limit is cut to exactly 120 characters and marked with the ellipsis before the quote.
        Assert::same(ValuePrinter::print($value), "'" . \str_repeat('a', 120) . "…'");
    }

    #[DataSet([[], '[]'], 'an empty array')]
    #[DataSet([[1, 2, 3], '[1, 2, 3]'], 'a list keeps its values')]
    #[DataSet([['a' => 1], "['a' => 1]"], 'an assoc array shows key and value')]
    #[DataSet([[1 => 'a', 0 => 'b'], "[1 => 'a', 0 => 'b']"], 'out-of-order int keys read as assoc')]
    #[DataSet([[1, 2, 3, 4, 5], '[1, 2, 3, 4, 5]'], 'five elements are all shown')]
    #[DataSet([[1, 2, 3, 4, 5, 6], '[1, 2, 3, 4, 5, …]'], 'the sixth element collapses to an ellipsis')]
    #[DataSet([[[[1]]], '[[array(1)]]'], 'an array past max depth becomes its count')]
    public function anArrayIsFlattenedToAShallowDepth(mixed $value, string $expected): void
    {
        Assert::same(ValuePrinter::print($value), $expected);
    }

    public function anEnumIsNamedByItsClassAndCase(): void
    {
        Assert::same(ValuePrinter::print(ValuePrinterColor::Red), '\\' . ValuePrinterColor::class . '::Red');
    }

    public function aStringableObjectShowsWhatItSaysAboutItself(): void
    {
        Assert::same(
            ValuePrinter::print(new ValuePrinterGreeter()),
            '\\' . ValuePrinterGreeter::class . "('hello')",
        );
    }

    public function aStringableObjectPastMaxDepthIsNamedByItsClassAlone(): void
    {
        // Nested twice, the object sits at max depth, where dumping its rendered form would be neither
        // short nor safe, so only the class name remains.
        Assert::same(
            ValuePrinter::print([[new ValuePrinterGreeter()]]),
            '[[\\' . ValuePrinterGreeter::class . ']]',
        );
    }

    public function aDateTimeShowsItselfInAtomFormat(): void
    {
        $date = new \DateTimeImmutable('2020-01-02T03:04:05+00:00');

        Assert::same(
            ValuePrinter::print($date),
            '\\DateTimeImmutable(' . $date->format(\DATE_ATOM) . ')',
        );
    }

    public function aPlainObjectIsNamedByItsClass(): void
    {
        Assert::same(ValuePrinter::print(new \stdClass()), '\\stdClass');
    }

    public function anOpenResourceStatesItsType(): void
    {
        $handle = \fopen('php://memory', 'r');

        try {
            Assert::same(ValuePrinter::print($handle), 'resource(stream)');
        } finally {
            \is_resource($handle) and \fclose($handle);
        }
    }

    public function aClosedResourceFallsBackToItsDebugType(): void
    {
        $handle = \fopen('php://memory', 'r');
        \fclose($handle);

        // A closed resource is no longer a live resource, so it drops through to the debug type.
        Assert::same(ValuePrinter::print($handle), 'resource (closed)');
    }

    #[DataSet(['x', 'string'], 'a string')]
    #[DataSet([42, 'int'], 'an int')]
    #[DataSet([[1], 'array'], 'an array')]
    public function theTypeIsReadTheWayAReaderExpects(mixed $value, string $expected): void
    {
        Assert::same(ValuePrinter::type($value), $expected);
    }

    public function theTypeOfAnObjectIsItsClassName(): void
    {
        Assert::same(ValuePrinter::type(new \stdClass()), 'stdClass');
    }
}

enum ValuePrinterColor
{
    case Red;
    case Blue;
}

final class ValuePrinterGreeter implements \Stringable
{
    public function __toString(): string
    {
        return 'hello';
    }
}
