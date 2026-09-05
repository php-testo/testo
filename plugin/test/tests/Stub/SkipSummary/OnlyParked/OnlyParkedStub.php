<?php

declare(strict_types=1);

namespace Tests\Test\Stub\SkipSummary\OnlyParked;

use Testo\Test;
use Testo\Test\Skip;

/**
 * A catalog consisting of parked tests only: such a run must be a success (exit 0).
 */
#[Test]
#[Skip('everything here is parked')]
final class OnlyParkedStub
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
