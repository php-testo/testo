<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Expect;
use Testo\Test;

final class AssertNotNull
{
    #[Test]
    public function checkNotNullValues(): void
    {
        Assert::notNull(0);
        Assert::notNull('');
        Assert::notNull(false);
        Assert::notNull([]);
    }

    #[Test]
    public function checkNullFails(): void
    {
        Expect::exception(AssertionException::class);
        Assert::notNull(null);
    }
}
