<?php

declare(strict_types=1);

namespace Tests\Spec\Stub;

use Testo\Assert;
use Testo\Spec;
use Testo\Spec\SpecHeader;
use Testo\Test;

final class MiscStub
{
    #[Test]
    #[Spec(story: 'A noted, but unnumbered, behaviour.')]
    #[SpecHeader(title: 'A side note')]
    public function notedBehaviour(): void
    {
        Assert::true(true);
    }

    #[Test]
    #[Spec(story: 'An unnumbered behaviour with no header at all.')]
    public function plainBehaviour(): void
    {
        Assert::true(true);
    }
}
