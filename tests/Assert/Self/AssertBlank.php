<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Attribute\Test;

/**
 * Assertion examples.
 */
final class AssertBlank
{
    #[Test]
    public function checkBlankData(): void
    {
        Assert::blank([]);
        Assert::blank("");
        Assert::blank(null);
        Assert::blank(new \ArrayIterator());
    }

    #[Test]
    public function checkZeroIsNotBlank(): void
    {
        Assert::exception(Assert\State\AssertException::class);
        Assert::blank(0);
    }

    #[Test]
    public function checkZeroStringIsNotBlank(): void
    {
        Assert::exception(Assert\State\AssertException::class);
        Assert::blank("0");
    }

    #[Test]
    public function checkFalseIsNotBlank(): void
    {
        Assert::exception(Assert\State\AssertException::class);
        Assert::blank(false);
    }
}
