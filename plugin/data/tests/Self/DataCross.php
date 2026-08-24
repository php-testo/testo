<?php

declare(strict_types=1);

namespace Tests\Data\Self;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataCross as DataCrossImpl;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
use Testo\Data\Internal\DataProviderInterceptor;
use Testo\Test;

/**
 * @see DataCrossImpl
 */
#[Test]
#[Covers(DataCrossImpl::class)]
#[Covers(DataProviderInterceptor::class)]
final class DataCross
{
    public static function numbersProvider(): array
    {
        return [
            [1, 2],
            [3, 4],
        ];
    }

    public static function lettersProvider(): iterable
    {
        yield 'ab' => ['a', 'b'];
        yield 'cd' => ['c', 'd'];
        yield 'ef' => ['e', 'f'];
    }

    /**
     * Cartesian product of two providers yields every combination of their rows
     * (2 number sets x 3 letter sets = 6 combinations).
     */
    #[\Testo\Data\DataCross(
        new DataProvider('numbersProvider'),
        new DataProvider('lettersProvider'),
    )]
    public function crossProduct(int $a, int $b, string $c, string $d): void
    {
        Assert::true(\in_array([$a, $b, $c, $d], [
            [1, 2, 'a', 'b'],
            [1, 2, 'c', 'd'],
            [1, 2, 'e', 'f'],
            [3, 4, 'a', 'b'],
            [3, 4, 'c', 'd'],
            [3, 4, 'e', 'f'],
        ], true));
    }

    /**
     * Inline DataSet axes and a DataProvider axis cross together
     * (1 DataSet x 3 letters x 1 DataSet = 3 combinations).
     */
    #[\Testo\Data\DataCross(
        new DataSet([true], 'yes'),
        new DataProvider('lettersProvider'),
        new DataSet([42], 'answer'),
    )]
    public function mixedProviders(bool $flag, string $c, string $d, int $number): void
    {
        Assert::true($flag);
        Assert::same($number, 42);
        Assert::true(\in_array([$c, $d], [
            ['a', 'b'],
            ['c', 'd'],
            ['e', 'f'],
        ], true));
    }
}
