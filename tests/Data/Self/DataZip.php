<?php

declare(strict_types=1);

namespace Tests\Data\Self;

use Testo\Assert;
use Testo\Attribute\Test;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;

final class DataZip
{
    public static function numbersProvider(): array
    {
        return [
            [1, 2],
            [3, 4],
            [5, 6],
        ];
    }

    public static function aFewLettersProvider(): iterable
    {
        yield 'ab' => ['a', 'b'];
        yield 'cd' => ['c', 'd'];
    }

    public static function lettersProvider(): iterable
    {
        yield 'ab' => ['a', 'b'];
        yield 'cd' => ['c', 'd'];
        yield 'ef' => ['e', 'f'];
    }

    #[Test]
    #[\Testo\Data\DataZip(
        new DataProvider('numbersProvider'),
        new DataProvider('lettersProvider'),
    )]
    public function sum(int $a, int $b, string $c, string $d): void
    {
        Assert::true(\in_array([$a, $b, $c, $d], [
            [1, 2, 'a', 'b'],
            [3, 4, 'c', 'd'],
            [5, 6, 'e', 'f'],
        ], true));
    }

    #[Test]
    #[\Testo\Data\DataZip(
        new DataProvider('numbersProvider'),
        new DataProvider('aFewLettersProvider'),
    )]
    public function sumFew(int $a, int $b, string $c, string $d): void
    {
        Assert::true(\in_array([$a, $b, $c, $d], [
            [1, 2, 'a', 'b'],
            [3, 4, 'c', 'd'],
        ], true));
    }

    #[Test]
    #[\Testo\Data\DataZip(
        new DataSet([true], 'yes'),
        new DataProvider('lettersProvider'),
        new DataSet([42], 'answer'),
    )]
    public function mixedProviders(bool $flag, string $c, string $d, int $number): void
    {
        // DataSet has only 1 element, so zip stops after first iteration
        Assert::true($flag);
        Assert::same('a', $c);
        Assert::same('b', $d);
        Assert::same(42, $number);
    }
}
