<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Attribute\Test;
use Testo\Expect;

/**
 * Assertion examples.
 */
final class AssertNumeric
{
    #[Test]
    public function checkDataType(): void
    {
        // These assertions check incoming data type
        Assert::int(42);
        Assert::float(42.1);
    }

    #[Test]
    public function checkGreaterThan(): void
    {
        // actual is greater than min threshold
        Assert::int(42)->greaterThan(41);
        Assert::float(42.1)->greaterThan(42.0);

        Expect::exception(AssertionException::class);
        Assert::int(42)->greaterThan(43);

        Expect::exception(AssertionException::class);
        Assert::float(42.1)->greaterThan(42.2);
    }

    #[Test]
    public function checkGreaterThanOrEqual(): void
    {
        // actual is greater than or equal to min threshold
        Assert::int(42)->greaterThanOrEqual(41);
        Assert::int(42)->greaterThanOrEqual(42);

        Assert::float(42.1)->greaterThanOrEqual(42.0);
        Assert::float(42.1)->greaterThanOrEqual(42.1);

        Expect::exception(AssertionException::class);
        Assert::int(42)->greaterThanOrEqual(43);

        Expect::exception(AssertionException::class);
        Assert::float(42.1)->greaterThanOrEqual(43.0);
    }

    #[Test]
    public function checkLessThan(): void
    {
        // actual is less than max threshold
        Assert::int(42)->lessThan(43);
        Assert::float(42.1)->lessThan(42.2);

        Expect::exception(AssertionException::class);
        Assert::int(42)->lessThan(42);

        Expect::exception(AssertionException::class);
        Assert::int(42.1)->lessThan(42.0);
    }

    #[Test]
    public function checkLessThanOrEqual(): void
    {
        // actual is less than or equal to max threshold
        Assert::int(41)->lessThanOrEqual(42);
        Assert::int(42)->lessThanOrEqual(42);

        Assert::float(42.0)->lessThanOrEqual(42.1);
        Assert::float(42.1)->lessThanOrEqual(42.1);

        Expect::exception(AssertionException::class);
        Assert::int(43)->lessThanOrEqual(42);

        Expect::exception(AssertionException::class);
        Assert::float(42.1)->lessThanOrEqual(42.0);
    }
}
