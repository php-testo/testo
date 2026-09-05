<?php

declare(strict_types=1);

namespace Tests\Test\Stub\Skip;

use Testo\Test;
use Testo\Test\Skip;

#[Test]
#[Skip('the whole case is parked')]
final class SkipClassLevelStub
{
    public function firstParked(): void
    {
        throw new \LogicException('Must never run: the case is parked.');
    }

    public function secondParked(): void
    {
        throw new \LogicException('Must never run: the case is parked.');
    }
}
