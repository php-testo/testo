<?php

declare(strict_types=1);

namespace Tests\Test\Stub\Skip;

use Testo\Test;

#[Test]
final class SkipTraitStub
{
    use SkipMarkerTrait;

    public function parked(): void
    {
        throw new \LogicException('Must never run: the case is parked via the trait.');
    }
}
