<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Attribute\Test;
use Testo\Expect;

/**
 * @see Assert::array()
 */
final class AssertArray
{
    #[Test]
    public function checkArrayType(): void
    {
        // This assertion checks incoming data type
        Assert::array([1, 2, 3]);
        Assert::array([]);
    }

    #[Test]
    public function checkIterableTraitMethods(): void
    {
        Assert::array([1, 2, 3])->contains(3)->allOf('int')->sameSizeAs([4,5,6])->hasCount(3);
    }

    #[Test]
    public function checkHasKey(): void
    {
        Assert::array(['key' => 'value', 'abc' => 'value2'])->hasKeys('key');

        Expect::exception(Assert\State\Assertion\AssertionException::class);
        Assert::array(['key' => 'value', 'abc' => 'value2'])->hasKeys('key2');
    }

    #[Test]
    public function assertIsList(): void
    {
        Assert::array([1, 2, 3])->isList();

        Expect::exception(Assert\State\Assertion\AssertionException::class);
        Assert::array(['key' => 'value', 'abc' => 'value2'])->isList();
    }

    #[Test]
    public function assertEvery(): void
    {
        Assert::array([1, 2, 3])->every(fn ($value) => is_int($value));

        Expect::exception(Assert\State\Assertion\AssertionException::class);
        Assert::array([1, 2, 3, 'testo'])->every(fn ($value) => is_int($value));
    }
}
