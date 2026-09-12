<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Test;

/**
 * Overrides a `#[Skip]`-marked method without repeating the attribute: the skip is inherited
 * from the prototype in {@see SkipOverriddenMethodParentStub}, reason included.
 */
#[Test]
final class SkipOverridingMethodStub extends SkipOverriddenMethodParentStub
{
    #[\Override]
    public function skipped(): void
    {
        throw new \LogicException('Must never run: the test is skipped via the overridden method.');
    }
}
