<?php

declare(strict_types=1);

namespace Tests\Data\Unit\Fixture;

use Testo\Data\DataCross;
use Testo\Data\DataProvider;
use Testo\Data\DataUnion;
use Testo\Data\DataZip;

/**
 * Fixture exercising the combinator data providers.
 *
 * Each combinator ({@see DataZip}, {@see DataCross}, {@see DataUnion}) folds the two
 * providers below in its own way, so {@see \Testo\Data\Internal\DataProviderInterceptor}
 * can be checked for the number and shape of the data sets it expands.
 */
final class CombinatorTarget
{
    /** @return array<non-empty-string, array{int}> */
    public static function letters(): array
    {
        return ['a' => [10], 'b' => [20]];
    }

    /** @return array<non-empty-string, array{int}> */
    public static function numbers(): array
    {
        return ['x' => [1], 'y' => [2]];
    }

    /** @return array<never, never> */
    public static function noRows(): array
    {
        return [];
    }

    #[DataCross(new DataProvider('noRows'), new DataProvider('numbers'))]
    public function crossedWithEmpty(int $a, int $b): void {}

    #[DataCross]
    public function crossedNothing(): void {}

    #[DataZip(new DataProvider('letters'), new DataProvider('numbers'))]
    public function zipped(int $a, int $b): void {}

    #[DataCross(new DataProvider('letters'), new DataProvider('numbers'))]
    public function crossed(int $a, int $b): void {}

    #[DataUnion(new DataProvider('letters'), new DataProvider('numbers'))]
    public function unioned(int $value): void {}
}
