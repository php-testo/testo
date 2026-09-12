<?php

declare(strict_types=1);

namespace Tests\Skip\Unit\Fixture;

use Testo\Skip;

/**
 * Fixture with a class-level `#[Skip]` and method-level overrides.
 *
 * Used by {@see \Tests\Skip\Unit\Internal\SkipInterceptorTest}: a class-level `#[Skip]`
 * skips every test; a method-level `#[Skip]` wins over the class-level one, reason included —
 * also when its own reason is empty.
 */
#[Skip('entire case is skipped')]
final class SkipClassLevelFixture
{
    public function first(): void {}

    #[Skip('method beats class')]
    public function second(): void {}

    #[Skip]
    public function third(): void {}
}
