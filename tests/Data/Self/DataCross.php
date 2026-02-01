<?php

declare(strict_types=1);

namespace Tests\Data\Self;

use Testo\Application\Attribute\Test;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;

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

    #[Test]
    #[\Testo\Data\DataCross(
        new DataProvider('numbersProvider'),
        new DataProvider('lettersProvider'),
    )]
    public function crossProduct(int $a, int $b, string $c, string $d): void
    {
        // 2 number sets × 3 letter sets = 6 combinations
        Assert::true(\in_array([$a, $b, $c, $d], [
            [1, 2, 'a', 'b'],
            [1, 2, 'c', 'd'],
            [1, 2, 'e', 'f'],
            [3, 4, 'a', 'b'],
            [3, 4, 'c', 'd'],
            [3, 4, 'e', 'f'],
        ], true));
    }

    #[Test]
    #[\Testo\Data\DataCross(
        new DataSet([true], 'yes'),
        new DataProvider('lettersProvider'),
        new DataSet([42], 'answer'),
    )]
    public function mixedProviders(bool $flag, string $c, string $d, int $number): void
    {
        // 1 DataSet × 3 letters × 1 DataSet = 3 combinations
        Assert::true($flag);
        Assert::same(42, $number);
        Assert::true(\in_array([$c, $d], [
            ['a', 'b'],
            ['c', 'd'],
            ['e', 'f'],
        ], true));
    }
}
