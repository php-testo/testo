<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::false()
 */
#[Test]
#[Covers(Assert::class, 'false')]
final class AssertFalse
{
    public function exactlyFalse(): void
    {
        Assert::false(false);
    }

    public function trueFails(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('expected `false`, got `true`');
        Assert::false(true);
    }

    public function falsyButNotFalseFails(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('expected `false`, got `0`');
        Assert::false(0);
    }
}
