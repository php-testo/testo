<?php

declare(strict_types=1);

namespace Tests\Data\Self;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
use Testo\Data\DataZip as DataZipImpl;
use Testo\Data\Internal\DataProviderInterceptor;
use Testo\Test;

/**
 * @see DataZipImpl
 */
#[Test]
#[Covers(DataZipImpl::class)]
#[Covers(DataProviderInterceptor::class)]
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

    /**
     * Two equal-length providers are paired by index, one row from each per iteration.
     */
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

    /**
     * When providers differ in length, zipping stops at the shortest axis (2 rows here).
     */
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

    /**
     * A single-row DataSet axis caps the zip after one iteration regardless of longer axes.
     */
    #[\Testo\Data\DataZip(
        new DataSet([true], 'yes'),
        new DataProvider('lettersProvider'),
        new DataSet([42], 'answer'),
    )]
    public function mixedProviders(bool $flag, string $c, string $d, int $number): void
    {
        Assert::true($flag);
        Assert::same($c, 'a');
        Assert::same($d, 'b');
        Assert::same($number, 42);
    }
}
