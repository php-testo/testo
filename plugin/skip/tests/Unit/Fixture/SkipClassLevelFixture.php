<?php

declare(strict_types=1);

namespace Tests\Skip\Unit\Fixture;

use Testo\Skip;

/**
 * Fixture with a class-level `#[Skip]` and method-level overrides.
 *
 * Used by {@see \Tests\Skip\Unit\Internal\SkipLocatorInterceptorTest}: a class-level `#[Skip]`
 * flags every test, whatever the method-level overrides say about the reason.
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
