<?php

declare(strict_types=1);

namespace Tests\Sample\Self;

use Testo\Expect;

/**
 * @see Expect::leaks()
 */
final class TestInline
{
    #[\Testo\Sample\TestInline(arguments: [1, 1], result: 2)]
    #[\Testo\Sample\TestInline(arguments: [40, 2], result: 42)]
    public function sum(int $a, int $b): int
    {
        return $a + $b;
    }

    #[\Testo\Sample\TestInline(arguments: [])]
    public function void(): void {}

    #[\Testo\Sample\TestInline(arguments: ['b' => 'foo', 'a' => 'bar'], result: 'bar-foo')]
    #[\Testo\Sample\TestInline(arguments: ['foo', 'bar'], result: 'foo-bar')]
    public function concat(string $a, string $b): string
    {
        return $a . '-' . $b;
    }
}
