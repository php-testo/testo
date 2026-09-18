<?php

declare(strict_types=1);

namespace Tests\Skip\Unit\Fixture;

use Testo\Skip;

/**
 * An override repeating `#[Skip]` with its own reason.
 *
 * Used by {@see \Tests\Skip\Unit\Internal\SkipInterceptorTest}: only a resolution that stops at the
 * nearest declaration reports this reason instead of the prototype's.
 */
final class SkipOwnReasonOverrideFixture extends SkipOwnReasonOverridePrototype
{
    #[\Override]
    #[Skip('own reason of the override')]
    public function skipped(): void {}
}
