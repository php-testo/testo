<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Test;

/**
 * Assertion examples.
 */
final class AssertEquals
{
    #[Test]
    public function numbers(): void
    {
        Assert::equals(1, 1);
        Assert::equals(1.0, 1);
        Assert::equals(1, 1.0);
        Assert::equals(2, "2");
    }

    #[Test]
    public function arrays(): void
    {
        # Same
        Assert::equals([1, 2], [1, 2]);
        Assert::equals(
            ['a' => 1, 'b' => 2],
            ['a' => 1, 'b' => 2],
        );
        # Different order
        Assert::equals(
            ['a' => 1, 'b' => 2],
            ['b' => 2, 'a' => 1],
        );
    }

    #[Test]
    public function objects(): void
    {
        Assert::equals(
            (object)['b' => 2, 'a' => 1],
            (object)['a' => 1, 'b' => 2],
        );
    }
}
