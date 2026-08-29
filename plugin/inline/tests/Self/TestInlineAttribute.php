<?php

declare(strict_types=1);

namespace Tests\Inline\Self;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Inline\TestInline;

/**
 * @see TestInline
 */
#[Covers(TestInline::class)]
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

    /**
     * Named arguments are matched by parameter name, so declaration order does not matter.
     */
    #[TestInline(arguments: ['b' => 'foo', 'a' => 'bar'], result: 'bar-foo')]
    #[TestInline(arguments: ['foo', 'bar'], result: 'foo-bar')]
    private function concat(string $a, string $b): string
    {
        Assert::true(true);
        return $a . '-' . $b;
    }
}
