<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::notSame()
 */
#[Test]
#[Covers(Assert::class, 'notSame')]
final class AssertNotSame
{
    public function notIdentical(): void
    {
        Assert::notSame('2', 2);
        Assert::notSame(1, 2);
        Assert::notSame(new \stdClass(), new \stdClass());
    }

    public function identicalFails(): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('both values are identical');
        Assert::notSame(1, 1);
    }
}
