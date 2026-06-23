<?php

declare(strict_types=1);

namespace Tests\Spec\Stub;

use Testo\Assert;
use Testo\Spec;
use Testo\Spec\SpecHeader;
use Testo\Test;

/**
 * Stub with a class-level section header and method-level spec items: the first item auto-numbers
 * under the section, the second overrides only its title (still auto-numbered).
 */
#[SpecHeader(title: 'Checkout', number: '5')]
final class SpecStub
{
    #[Test]
    #[Spec(story: 'As a user I want X so that Y.')]
    public function methodLevelSpec(): void
    {
        Assert::true(true);
    }

    #[Test]
    #[Spec(story: 'Tax is included in the total.', tags: ['checkout'])]
    #[SpecHeader(title: 'Tax in total')]
    public function specWithHeader(): void
    {
        Assert::true(true);
    }
}
