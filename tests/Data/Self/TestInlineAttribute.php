<?php

declare(strict_types=1);

namespace Tests\Data\Self;

use Testo\Inline\TestInline;
use Webmozart\Assert\Assert;

final class TestInlineAttribute
{
    #[TestInline(arguments: [1, 1], result: 2)]
    #[TestInline(arguments: [40, 2], result: 42)]
    public function sum(int $a, int $b): int
    {
        return $a + $b;
    }

    #[TestInline(arguments: [])]
    public function void(): void {}

    #[TestInline(arguments: ['b' => 'foo', 'a' => 'bar'], result: 'bar-foo')]
    #[TestInline(arguments: ['foo', 'bar'], result: 'foo-bar')]
    private function concat(string $a, string $b): string
    {
        Assert::true(true);
        return $a . '-' . $b;
    }
}
