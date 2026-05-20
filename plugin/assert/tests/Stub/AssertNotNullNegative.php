<?php

declare(strict_types=1);

namespace Tests\Assert\Stub;

use Testo\Assert;
use Testo\Test;

/**
 * Stubs for negative {@see Assert::notNull()} scenarios.
 */
final class AssertNotNullNegative
{
    #[Test]
    public function nullFails(): void
    {
        Assert::notNull(null);
    }

    #[Test]
    public function nullFailsWithMessage(): void
    {
        Assert::notNull(null, 'Value must not be null.');
    }
}