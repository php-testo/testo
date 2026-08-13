<?php

declare(strict_types=1);

namespace Tests\Data\Unit\Fixture;

use Testo\Data\DataProvider;

/**
 * Fixture with a non-static (instance) data provider method.
 *
 * Used to verify that {@see \Testo\Data\Internal\DataProviderInterceptor} correctly
 * binds the provider method to the class instance when it is not static.
 */
final class NonStaticProviderTarget
{
    public function instanceProvider(): array
    {
        return [[10], [20]];
    }

    #[DataProvider('instanceProvider')]
    public function target(int $value): void {}
}
