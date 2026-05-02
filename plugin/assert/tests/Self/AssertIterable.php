<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::iterable()
 */
final class AssertIterable
{
    #[Test]
    public function checkIterableType(): void
    {
        // This assertion checks incoming data type
        Assert::iterable(new \ArrayIterator([1, 2, 3]));
        Assert::iterable([]);
    }

    #[Test]
    public function checkContains(): void
    {
        Assert::iterable(new \ArrayIterator([1, 2, 3]))->contains(3);
        Assert::iterable([1, 2, 3])->contains(3);
    }

    #[Test]
    public function checkSameSizeAs(): void
    {
        Assert::iterable(new \ArrayIterator([1, 2, 3]))->sameSizeAs(new \ArrayIterator(['a', 'b', 'c']));
        Assert::iterable(new \ArrayIterator([1, 2, 3]))->sameSizeAs(['a', 'b', 'c']);
    }

    #[Test]
    public function assertCount(): void
    {
        Assert::iterable(new \ArrayIterator([1, 2, 3]))->hasCount(3);
    }

    #[Test]
    public function checkAllOf(): void
    {
        Assert::iterable(new \ArrayIterator([1, 2, 3]))->allOf('integer');
        Assert::iterable(['a', 'b', 'c'])->allOf('string');

        Expect::exception(AssertionException::class);
        Assert::iterable([true, false, 'true'])->allOf('bool');
    }
}
