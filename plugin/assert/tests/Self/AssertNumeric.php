<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\Internal\Assertion\AssertFloat as AssertFloatImpl;
use Testo\Assert\Internal\Assertion\AssertInt as AssertIntImpl;
use Testo\Assert\Internal\Assertion\AssertNumeric as AssertNumericImpl;
use Testo\Assert\Internal\Assertion\Traits\NumericTrait;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::int()
 * @see Assert::float()
 * @see Assert::numeric()
 */
#[Test]
#[Covers(Assert::class, 'int')]
#[Covers(Assert::class, 'float')]
#[Covers(Assert::class, 'numeric')]
#[Covers(AssertIntImpl::class)]
#[Covers(AssertFloatImpl::class)]
#[Covers(AssertNumericImpl::class)]
#[Covers(NumericTrait::class)]
final class AssertNumeric
{
    public function checkDataType(): void
    {
        // These assertions check incoming data type
        Assert::int(42);
        Assert::float(42.1);
    }

    public function checkNumericDataType(): void
    {
        // numeric accepts integers, floats and numeric strings
        Assert::numeric(42);
        Assert::numeric(42.1);
        Assert::numeric('42');
        Assert::numeric('42.1');
        // a numeric string is normalised to a real number for comparisons
        Assert::numeric('42')->greaterThan(41)->lessThan(43);
    }

    #[DataSet(['abc'], 'non-numeric string')]
    #[DataSet([true], 'boolean')]
    #[DataSet([null], 'null')]
    #[DataSet([[1]], 'array')]
    public function checkNumericFails(mixed $value): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('is numeric');
        Assert::numeric($value);
    }

    public function greaterThan(): void
    {
        // actual is greater than min threshold
        Assert::int(42)->greaterThan(41);
        Assert::float(42.1)->greaterThan(42.0);
    }

    #[DataSet([42, 43], 'int not greater')]
    #[DataSet([42.1, 42.2], 'float not greater')]
    public function greaterThanFails(int|float $actual, int|float $min): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        \is_int($actual)
            ? Assert::int($actual)->greaterThan($min, 'my wonderful message')
            : Assert::float($actual)->greaterThan($min, 'my wonderful message');
    }

    public function greaterThanOrEqual(): void
    {
        // actual is greater than or equal to min threshold
        Assert::int(42)->greaterThanOrEqual(41);
        Assert::int(42)->greaterThanOrEqual(42);

        Assert::float(42.1)->greaterThanOrEqual(42.0);
        Assert::float(42.1)->greaterThanOrEqual(42.1);
    }

    #[DataSet([42, 43], 'int not greater or equal')]
    #[DataSet([42.1, 43.0], 'float not greater or equal')]
    public function greaterThanOrEqualFails(int|float $actual, int|float $min): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        \is_int($actual)
            ? Assert::int($actual)->greaterThanOrEqual($min, 'my wonderful message')
            : Assert::float($actual)->greaterThanOrEqual($min, 'my wonderful message');
    }

    public function lessThan(): void
    {
        // actual is less than max threshold
        Assert::int(42)->lessThan(43);
        Assert::float(42.1)->lessThan(42.2);
    }

    #[DataSet([42, 42], 'int equal to max')]
    #[DataSet([42.1, 42.0], 'float not less')]
    public function lessThanFails(int|float $actual, int|float $max): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        \is_int($actual)
            ? Assert::int($actual)->lessThan($max, 'my wonderful message')
            : Assert::float($actual)->lessThan($max, 'my wonderful message');
    }

    public function lessThanOrEqual(): void
    {
        // actual is less than or equal to max threshold
        Assert::int(41)->lessThanOrEqual(42);
        Assert::int(42)->lessThanOrEqual(42);

        Assert::float(42.0)->lessThanOrEqual(42.1);
        Assert::float(42.1)->lessThanOrEqual(42.1);
    }

    #[DataSet([43, 42], 'int not less or equal')]
    #[DataSet([42.1, 42.0], 'float not less or equal')]
    public function lessThanOrEqualFails(int|float $actual, int|float $max): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        \is_int($actual)
            ? Assert::int($actual)->lessThanOrEqual($max, 'my wonderful message')
            : Assert::float($actual)->lessThanOrEqual($max, 'my wonderful message');
    }
}
