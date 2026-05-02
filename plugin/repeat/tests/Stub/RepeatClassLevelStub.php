<?php

declare(strict_types=1);

namespace Tests\Repeat\Stub;

use Testo\Assert;
use Testo\Repeat;
use Testo\Test;

/**
 * Stub with class-level #[Repeat] attribute.
 */
#[Repeat(times: 2)]
final class RepeatClassLevelStub
{
    #[Test]
    public function firstTest(): void
    {
        Assert::true(true);
    }

    #[Test]
    public function secondTest(): void
    {
        Assert::true(true);
    }
}
