<?php

declare(strict_types=1);

namespace Tests\Data\Unit\Fixture;

use Testo\Data\DataProvider;

/**
 * Fixture with malformed data providers.
 *
 * Drives the validation and guard paths of {@see \Testo\Data\Internal\DataProviderInterceptor}:
 * an unknown provider method name, a provider returning a non-iterable, and a provider whose
 * iterable yields a non-array data set.
 */
final class InvalidProviderTarget
{
    public static function returnsScalar(): int
    {
        return 42;
    }

    /** @return array{array{int}, string} */
    public static function withNonArrayDataSet(): array
    {
        return [[1], 'oops'];
    }

    #[DataProvider('missingProviderMethod')]
    public function unknownMethod(int $value): void {}

    #[DataProvider('returnsScalar')]
    public function scalarReturn(int $value): void {}

    #[DataProvider('withNonArrayDataSet')]
    public function nonArrayDataSet(int $value): void {}
}
