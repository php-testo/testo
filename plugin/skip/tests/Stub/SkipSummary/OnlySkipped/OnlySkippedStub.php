<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\SkipSummary\OnlySkipped;

use Testo\Test;
use Testo\Skip;

/**
 * A case consisting of skipped tests only: such a run must be a success (exit 0).
 */
#[Test]
#[Skip('everything here is skipped')]
final class OnlySkippedStub
{
    public function firstSkipped(): void
    {
        throw new \LogicException('Must never run: the case is skipped.');
    }

    public function secondSkipped(): void
    {
        throw new \LogicException('Must never run: the case is skipped.');
    }
}
