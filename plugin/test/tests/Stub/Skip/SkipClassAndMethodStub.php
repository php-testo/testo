<?php

declare(strict_types=1);

namespace Tests\Test\Stub\Skip;

use Testo\Test;
use Testo\Test\Skip;

#[Test]
#[Skip('class-wide reason')]
final class SkipClassAndMethodStub
{
    #[Skip('method-specific reason')]
    public function ownReason(): void
    {
        throw new \LogicException('Must never run: the test is parked.');
    }

    public function classReason(): void
    {
        throw new \LogicException('Must never run: the case is parked.');
    }

    #[Skip]
    public function emptyOwnReason(): void
    {
        throw new \LogicException('Must never run: the test is parked.');
    }
}
