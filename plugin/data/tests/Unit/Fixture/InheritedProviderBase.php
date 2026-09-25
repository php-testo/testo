<?php

declare(strict_types=1);

namespace Tests\Data\Unit\Fixture;

use Testo\Data\DataProvider;

/**
 * Base declaring a data-driven test whose provider only a subclass implements.
 *
 * Drives the provider lookup of {@see \Testo\Data\Internal\DataProviderInterceptor}: the name resolves
 * against the class the test runs in ({@see InheritedProviderTarget}), not the declaring one.
 */
abstract class InheritedProviderBase
{
    /** @return iterable<array{int}> */
    abstract public static function values(): iterable;

    #[DataProvider('values')]
    public function target(int $value): void {}
}
