<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Skip;
use Testo\Test;

/**
 * Overrides a `#[Skip]`-marked method and declares its own reason: the override's reason is the
 * one reported, not the one it inherits from {@see SkipOverriddenMethodParentStub}.
 *
 * Both declarations spawn an interceptor, so this stub is what tells a resolution by reflection
 * apart from one that trusts whichever occurrence the pipeline happened to keep.
 */
#[Test]
final class SkipOverridingMethodOwnReasonStub extends SkipOverriddenMethodParentStub
{
    #[\Override]
    #[Skip('own reason of the overriding method')]
    public function skipped(): void
    {
        throw new \LogicException('Must never run: the test is skipped.');
    }
}
