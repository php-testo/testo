<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Data\DataProvider;
use Testo\Test;
use Testo\Skip;

/**
 * Stub with a data-driven test skipped by {@see Skip}:
 * {@see \Testo\Data\Internal\DataProviderInterceptor} must never expand the test's data sets.
 *
 * The provider counts its calls before returning anything, so the counter tells "never
 * called" apart from "called but not iterated" — a generator body would only run on iteration.
 */
#[Test]
final class SkipWithDataProviderStub
{
    public static int $providerCalls = 0;

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

    #[Skip('data-driven test is skipped as a whole')]
    #[DataProvider('provide')]
    public function skipped(int $value): void
    {
        throw new \LogicException('Must never run: the test is skipped.');
    }
}
