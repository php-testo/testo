<?php

declare(strict_types=1);

namespace Tests\Spec\Unit\Fixture;

use Testo\Spec;
use Testo\Spec\SpecHeader;

/**
 * Fixture exercising class- and method-level {@see SpecHeader} reflection. Not a runnable test case
 * (no `#[Test]`): it only provides reflections for the interceptor unit test.
 */
#[SpecHeader('5', 'Checkout')]
final class HeaderedCase
{
    #[Spec(story: 'Tax is included in the total.')]
    #[SpecHeader('5.1', 'Tax in total')]
    public function withHeader(): void {}

    #[Spec(story: 'Coupon lowers the total.')]
    public function withoutHeader(): void {}
}
