<?php

declare(strict_types=1);

namespace Tests\Spec\Stub;

use Testo\Assert;
use Testo\Spec;
use Testo\Spec\SpecHeader;
use Testo\Test;

#[SpecHeader(title: 'Authentication', number: '2')]
final class AuthStub
{
    #[Test]
    #[Spec(story: 'A user logs in with valid credentials.')]
    public function login(): void
    {
        Assert::true(true);
    }

    #[Test]
    #[Spec(story: 'A user logs out.')]
    public function logout(): void
    {
        Assert::true(true);
    }
}
