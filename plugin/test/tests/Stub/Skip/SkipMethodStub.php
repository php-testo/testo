<?php

declare(strict_types=1);

namespace Tests\Test\Stub\Skip;

use Testo\Assert;
use Testo\Test;
use Testo\Test\Skip;

#[Test]
final class SkipMethodStub
{
    #[Skip('broken by the pricing rework, see ISSUE-123')]
    public function parked(): void
    {
        throw new \LogicException('Must never run: the test is parked.');
    }

    #[Skip]
    public function parkedNoReason(): void
    {
        throw new \LogicException('Must never run: the test is parked.');
    }

    public function enabled(): void
    {
        // Control neighbor: stays runnable next to the parked ones.
        Assert::true(true);
    }
}
