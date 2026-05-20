<?php

declare(strict_types=1);

namespace Tests\Assert\Stub;

use Testo\Assert;
use Testo\Test;

/**
 * Stubs for positive {@see Assert::notNull()} scenarios.
 */
final class AssertNotNullPositive
{
    #[Test]
    public function falsyNonNullValues(): void
    {
        Assert::notNull(0);
        Assert::notNull('');
        Assert::notNull(false);
        Assert::notNull([]);
        Assert::notNull(0.0);
    }
}
