<?php

declare(strict_types=1);

namespace Tests\Data\Unit\Fixture;

/**
 * Implements the provider {@see InheritedProviderBase} declares abstract.
 */
final class InheritedProviderTarget extends InheritedProviderBase
{
    #[\Override]
    public static function values(): iterable
    {
        yield 'one' => [1];
        yield 'two' => [2];
    }
}
