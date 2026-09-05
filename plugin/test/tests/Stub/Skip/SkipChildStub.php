<?php

declare(strict_types=1);

namespace Tests\Test\Stub\Skip;

use Testo\Test;

#[Test]
final class SkipChildStub extends SkipParentStub
{
    public function parked(): void
    {
        throw new \LogicException('Must never run: the case is parked via the parent.');
    }
}
