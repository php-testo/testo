<?php

declare(strict_types=1);

namespace Tests\Spec\Unit\Fixture;

use Testo\Spec;
use Testo\Spec\SpecHeader;

/**
 * Fixture exercising class- and method-level {@see SpecHeader} reflection. Not a runnable test case
 * (no `#[Test]`): it only provides reflections for the interceptor unit test.
 */
#[SpecHeader(title: 'Checkout', number: '5')]
final class HeaderedCase
{
    #[Spec(story: 'Tax is included in the total.')]
    #[SpecHeader(title: 'Tax in total', number: '5.1')]
    public function withHeader(): void {}

    #[Spec(story: 'Coupon lowers the total.')]
    public function withoutHeader(): void {}
}
