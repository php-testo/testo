<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Test;

/**
 * A concrete case without its own `#[Skip]`: the attribute comes from {@see SkipParentStub}.
 */
#[Test]
final class SkipChildStub extends SkipParentStub
{
    public function skipped(): void
    {
        throw new \LogicException('Must never run: the case is skipped via the parent.');
    }
}
