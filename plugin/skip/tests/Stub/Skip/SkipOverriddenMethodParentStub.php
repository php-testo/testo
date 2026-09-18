<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Skip;

/**
 * Abstract, so the locator never discovers it as its own case — the method-level `#[Skip]`
 * reaches the case only through {@see SkipOverridingMethodStub}, which overrides the method.
 */
abstract class SkipOverriddenMethodParentStub
{
    #[Skip('inherited from the overridden method')]
    public function skipped(): void
    {
        throw new \LogicException('Must never run: the parent method is skipped.');
    }
}
