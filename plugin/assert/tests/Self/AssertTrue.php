<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::true()
 */
#[Test]
#[Covers(Assert::class, 'true')]
final class AssertTrue
{
    public function exactlyTrue(): void
    {
        Assert::true(true);
    }

    public function falseFails(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('expected `true`, got `false`');
        Assert::true(false);
    }

    public function truthyButNotTrueFails(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('expected `true`, got `1`');
        Assert::true(1);
    }
}
