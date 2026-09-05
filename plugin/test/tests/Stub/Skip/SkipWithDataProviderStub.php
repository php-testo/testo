<?php

declare(strict_types=1);

namespace Tests\Test\Stub\Skip;

use Testo\Data\DataProvider;
use Testo\Test;
use Testo\Test\Skip;

/**
 * The provider counts its calls before returning anything, so the counter tells "never
 * called" apart from "called but not iterated" — a generator body would only run on iteration.
 */
final class SkipWithDataProviderStub
{
    public static int $providerCalls = 0;

    #[Test]
    #[Skip('data-driven test is parked as a whole')]
    #[DataProvider('provide')]
    public function parked(int $value): void
    {
        throw new \LogicException('Must never run: the test is parked.');
    }

    /**
     * @return array<non-empty-string, array{int}>
     */
    public static function provide(): array
    {
        ++self::$providerCalls;

        return [
            'one' => [1],
            'two' => [2],
        ];
    }
}
