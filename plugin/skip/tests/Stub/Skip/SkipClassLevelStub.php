<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Test;
use Testo\Skip;

/**
 * A class-level `#[Skip]`: both tests of the case are skipped with the class reason, proving the
 * attribute covers every test and not just the first one.
 */
#[Test]
#[Skip('the whole case is skipped')]
final class SkipClassLevelStub
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
