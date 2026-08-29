<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::same()
 */
#[Test]
#[Covers(Assert::class, 'same')]
final class AssertSame
{
    public function identical(): void
    {
        Assert::same(1, 1);
        Assert::same('a', 'a');
        $o = new \stdClass();
        Assert::same($o, $o);
    }

    public function differentFails(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('is the same as `2`');
        Assert::same(1, 2);
    }

    public function looseEqualButNotIdenticalFails(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('expected `2`, got `"2"`');
        Assert::same('2', 2);
    }
}
