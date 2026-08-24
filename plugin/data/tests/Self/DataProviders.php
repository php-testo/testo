<?php

declare(strict_types=1);

namespace Tests\Data\Self;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
use Testo\Data\Internal\DataProviderInterceptor;
use Testo\Test;

/**
 * @see DataProvider
 * @see DataSet
 */
#[Test]
#[Covers(DataProvider::class)]
#[Covers(DataSet::class)]
#[Covers(DataProviderInterceptor::class)]
final class DataProviders
{
    public static function numbersProvider(): array
    {
        return [
            [1, 2],
            [3, 4],
            [5, 6],
        ];
    }

    /**
     * Several DataProvider and DataSet attributes on one method are all collected:
     * a public provider, a private provider, a labeled DataSet and a named-arguments DataSet.
     */
    #[DataProvider('numbersProvider')]
    #[DataProvider('bigNumbersProvider')]
    #[DataSet([7, 8], 'seven-eight')]
    #[DataSet(['b' => 7, 'a' => 8])]
    public function sum(int $a, int $b): void
    {
        Assert::true(\in_array([$a, $b], [
            [1, 2],
            [3, 4],
            [5, 6],
            [100, 200],
            [300, 400],
            [500, 600],
            [7, 8],
        ], true) || ($a === 8 && $b === 7), 'Invalid data set provided.');
    }

    private static function bigNumbersProvider(): array
    {
        return [
            [100, 200],
            [300, 400],
            [500, 600],
        ];
    }
}
