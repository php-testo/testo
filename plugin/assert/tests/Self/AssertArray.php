<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\Internal\Assertion\AssertArray as AssertArrayImpl;
use Testo\Assert\Internal\Assertion\Traits\IterableTrait;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::array()
 */
#[Test]
#[Covers(Assert::class, 'array')]
#[Covers(AssertArrayImpl::class)]
#[Covers(IterableTrait::class)]
final class AssertArray
{
    public function checkArrayType(): void
    {
        // This assertion checks incoming data type
        Assert::array([1, 2, 3]);
        Assert::array([]);
    }

    public function notEmpty(): never
    {
        Assert::array([1, 2, 3])->notEmpty();

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::array([])->notEmpty('my wonderful message');
    }

    public function contains(): never
    {
        Assert::array([1, 2, 3])->contains(2);

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::array([1, 2, 3])->contains(4, 'my wonderful message');
    }

    public function notContains(): never
    {
        Assert::array([1, 2, 3])->notContains(4);

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::array([1, 2, 3])->notContains(2, 'my wonderful message');
    }

    public function sameSizeAs(): never
    {
        Assert::array([1, 2, 3])->sameSizeAs([4, 5, 6]);

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::array([1, 2, 3])->sameSizeAs([1, 2], 'my wonderful message');
    }

    public function allOf(): never
    {
        Assert::array([1, 2, 3])->allOf('int');

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::array([1, 2, 'testo'])->allOf('int', 'my wonderful message');
    }

    public function every(): never
    {
        Assert::array([1, 2, 3])->every(static fn($value) => \is_int($value));

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::array([1, 2, 3, 'testo'])->every(static fn($value) => \is_int($value), 'my wonderful message');
    }

    public function hasCount(): never
    {
        Assert::array([1, 2, 3])->hasCount(3);

        Expect::exception(AssertionException::class);
        Assert::array([1, 2, 3])->hasCount(2);
    }

    public function hasKeys(): void
    {
        Assert::array(['key' => 'value', 'abc' => 'value2'])->hasKeys('key');
        Assert::array(['key' => 'value', 'abc' => 'value2'])->hasKeys('key', 'abc');
    }

    /**
     * @param non-empty-list<int|string> $keys
     */
    #[DataSet([['missing']], 'single missing key')]
    #[DataSet([['key', 'missing']], 'one of several keys missing')]
    public function hasKeysFails(array $keys): never
    {
        Expect::exception(AssertionException::class);
        Assert::array(['key' => 'value'])->hasKeys(...$keys);
    }

    public function doesNotHaveKeys(): void
    {
        Assert::array(['key' => 'value'])->doesNotHaveKeys('missing');
        Assert::array(['key' => 'value'])->doesNotHaveKeys('missing', 'absent');
    }

    /**
     * @param non-empty-list<int|string> $keys
     */
    #[DataSet([['key']], 'single present key')]
    #[DataSet([['key', 'missing']], 'one of several keys present')]
    public function doesNotHaveKeysFails(array $keys): never
    {
        Expect::exception(AssertionException::class);
        Assert::array(['key' => 'value'])->doesNotHaveKeys(...$keys);
    }

    public function isList(): void
    {
        Assert::array([1, 2, 3])->isList();
        Assert::array([])->isList();
    }

    public function sameElementsAs(): void
    {
        Assert::array([1, 2, 3])->sameElementsAs([3, 2, 1]);
        Assert::array([])->sameElementsAs([]);
        // keys are discarded during canonicalization
        Assert::array(['a' => 1, 'b' => 2])->sameElementsAs([2, 1]);
        // nested arrays are canonicalized recursively
        Assert::array([[3, 2], [1]])->sameElementsAs([[1], [2, 3]]);
        // loose comparison, as with assertEqualsCanonicalizing
        Assert::array([1, 2])->sameElementsAs(['2', '1']);
        // accepts any iterable as the expected side
        Assert::array([1, 2, 3])->sameElementsAs(new \ArrayIterator([3, 1, 2]));
    }

    /**
     * @param array<mixed> $value
     * @param array<mixed> $expected
     */
    #[DataSet([[1, 2, 3], [1, 2]], 'different size')]
    #[DataSet([[1, 2, 3], [1, 2, 4]], 'different elements')]
    public function sameElementsAsFails(array $value, array $expected): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::array($value)->sameElementsAs($expected, 'my wonderful message');
    }

    /**
     * @param array<mixed> $value
     */
    #[DataSet([['key' => 'value']], 'associative array')]
    #[DataSet([[1 => 'a', 2 => 'b']], 'non-zero-based keys')]
    public function isListFails(array $value): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::array($value)->isList('my wonderful message');
    }
}
