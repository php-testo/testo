<?php

declare(strict_types=1);

namespace Tests\Skip\Unit\Fixture;

use Testo\Skip;

/**
 * The prototype {@see SkipOwnReasonOverrideFixture} overrides: it carries a `#[Skip]` of its own,
 * so both declarations are visible from the overriding method.
 */
abstract class SkipOwnReasonOverridePrototype
{
    #[Skip('reason of the prototype')]
    public function skipped(): void {}
}
