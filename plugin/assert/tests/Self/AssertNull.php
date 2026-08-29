<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::null()
 */
#[Test]
#[Covers(Assert::class, 'null')]
final class AssertNull
{
    public function exactlyNull(): void
    {
        Assert::null(null);
    }

    public function nonNullFails(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('expected `null`, got `42`');
        Assert::null(42);
    }

    public function falsyButNotNullFails(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('expected `null`, got `0`');
        Assert::null(0);
    }
}
