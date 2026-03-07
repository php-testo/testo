<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Attribute\Test;

/**
 * Assertion examples.
 */
final class AssertNotEquals
{
    #[Test]
    public function numbers(): void
    {
        Assert::notEquals(1, 2);
    }

    #[Test]
    public function arrays(): void
    {
        Assert::notEquals([2, 1], [1, 2]);
    }

    #[Test]
    public function objects(): void
    {
        Assert::notEquals(
            (object) ['a' => 1],
            (object) ['a' => 2],
        );
    }
}
