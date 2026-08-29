<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Html;

use Testo\Assert;
use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Output\Html\Internal\FailureMapper;
use Testo\Test;

/**
 * How a throwable is projected into the report: its location and the source line it points at, the
 * comparison diff when the failure knows both sides, and the flattened chain of what caused it.
 */
#[Test]
#[Covers(FailureMapper::class)]
final class FailureMapperTest
{
    public function aFailureCarriesItsClassMessageLocationSourceLineAndTrace(): void
    {
        $failure = self::boom();

        $data = (new FailureMapper())->map($failure);

        Assert::same($data['class'], \RuntimeException::class);
        Assert::same($data['message'], 'kaboom');
        Assert::string($data['file'])->contains('FailureMapperTest.php');
        Assert::true($data['line'] > 0, "line = {$data['line']}");
        // The line the failure points at, read back from the file — the one thing a stack frame cannot say.
        Assert::string($data['sourceLine'])->contains('kaboom');
        Assert::true($data['trace'] !== []);
        // A plain throwable knows neither a comparison nor a prior cause, so neither key appears.
        Assert::false(\array_key_exists('diff', $data));
        Assert::false(\array_key_exists('causedBy', $data));
    }

    public function aComparisonFailureCarriesBothSidesAndTheEditScript(): void
    {
        $failure = new ComparisonFailure(
            expected: "line1\nline2\nline3",
            actual: "line1\nCHANGED\nline3",
            value: 'value',
            assertion: 'is the same',
            context: '',
            reason: 'values differ',
        );

        $diff = (new FailureMapper())->map($failure)['diff'];

        Assert::same($diff['expected'], "line1\nline2\nline3");
        Assert::same($diff['actual'], "line1\nCHANGED\nline3");
        // A middle line changed: the shared lines are context, the old one deleted, the new one added.
        $ops = \array_column($diff['lines'], 'op');
        Assert::true(\in_array('ctx', $ops, true), 'has context op');
        Assert::true(\in_array('del', $ops, true), 'has delete op');
        Assert::true(\in_array('add', $ops, true), 'has add op');
        Assert::true(\in_array(['op' => 'del', 'text' => 'line2'], $diff['lines'], true), 'deletes the old line');
        Assert::true(\in_array(['op' => 'add', 'text' => 'CHANGED'], $diff['lines'], true), 'adds the new line');
    }

    public function thePreviousChainIsFlattenedIntoCausedByWithLocationsOnly(): void
    {
        $inner = new \LogicException('inner');
        $mid = new \RuntimeException('mid', 0, $inner);
        $outer = new \RuntimeException('outer', 0, $mid);

        $causedBy = (new FailureMapper())->map($outer)['causedBy'];

        // Outermost is the failure itself; only its ancestors are listed, nearest first.
        Assert::same(\count($causedBy), 2);
        Assert::same($causedBy[0]['class'], \RuntimeException::class);
        Assert::same($causedBy[0]['message'], 'mid');
        Assert::string($causedBy[0]['file'])->contains('FailureMapperTest.php');
        Assert::true($causedBy[0]['line'] > 0);
        Assert::same($causedBy[1]['class'], \LogicException::class);
        Assert::same($causedBy[1]['message'], 'inner');
    }

    #[DataSet(['line'], 'a line number below one has nothing to read')]
    #[DataSet(['file'], 'a file that is not there has nothing to read')]
    #[DataSet(['oversized'], 'a file too large to page in has nothing to read')]
    public function aFailureWithNoReadableSourceOmitsTheSourceLine(string $mode): void
    {
        $failure = new \RuntimeException('x');
        $cleanup = null;

        switch ($mode) {
            case 'line':
                self::setProperty($failure, 'line', 0);
                break;
            case 'file':
                self::setProperty($failure, 'file', \sys_get_temp_dir() . '/' . \uniqid('gone-', true) . '.php');
                break;
            case 'oversized':
                $path = \tempnam(\sys_get_temp_dir(), 'big');
                \file_put_contents($path, \str_repeat("x\n", 1_100_000)); // > 2 MiB
                self::setProperty($failure, 'file', $path);
                self::setProperty($failure, 'line', 1);
                $cleanup = $path;
                break;
        }

        try {
            $data = (new FailureMapper())->map($failure);
        } finally {
            $cleanup === null or @\unlink($cleanup);
        }

        Assert::false(\array_key_exists('sourceLine', $data));
    }

    public function aBlankSourceLineIsOmittedRatherThanReportedEmpty(): void
    {
        // The failure is pointed at the blank line directly below this assignment.
        $blankLine = __LINE__ + 1;

        $failure = new \RuntimeException('x');
        self::setProperty($failure, 'file', __FILE__);
        self::setProperty($failure, 'line', $blankLine);

        $data = (new FailureMapper())->map($failure);

        Assert::false(\array_key_exists('sourceLine', $data));
    }

    public function theSourceFileIsReadOnceAndReusedAcrossFailures(): void
    {
        $path = \tempnam(\sys_get_temp_dir(), 'src');
        \file_put_contents($path, "first line\nsecond line\n");

        $mapper = new FailureMapper();

        $firstFailure = new \RuntimeException('x');
        self::setProperty($firstFailure, 'file', $path);
        self::setProperty($firstFailure, 'line', 1);
        $first = $mapper->map($firstFailure);

        // Delete the file after the first read: a second read of the same path would find nothing,
        // so a source line from line 2 can only come from a cached read of the file.
        @\unlink($path);

        $secondFailure = new \RuntimeException('x');
        self::setProperty($secondFailure, 'file', $path);
        self::setProperty($secondFailure, 'line', 2);
        $second = $mapper->map($secondFailure);

        Assert::same($first['sourceLine'], 'first line');
        Assert::same($second['sourceLine'], 'second line');
    }

    private static function boom(): \RuntimeException
    {
        return new \RuntimeException('kaboom');
    }

    private static function setProperty(\Throwable $throwable, string $name, mixed $value): void
    {
        $property = new \ReflectionProperty(\Exception::class, $name);
        $property->setValue($throwable, $value);
    }
}
