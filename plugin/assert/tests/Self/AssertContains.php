<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::contains()
 */
#[Test]
#[Covers(Assert::class, 'contains')]
final class AssertContains
{
    public function found(): void
    {
        Assert::contains([1, 2, 3], 2);
        Assert::contains(new \ArrayIterator([1, 2, 3]), 3);
    }

    public function missingFails(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('`3` not found');
        Assert::contains([1, 2], 3);
    }

    public function looseMatchIsNotEnough(): never
    {
        // Search is strict (===), so a numeric string does not match an int.
        Expect::exception(AssertionException::class)
            ->withMessageContaining('`"2"` not found');
        Assert::contains([1, 2, 3], '2');
    }
}
