<?php

declare(strict_types=1);

namespace Tests\Testo\Self;

use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Assert\State\AssertException;
use Testo\Attribute\Test;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Retry\RetryPolicy;
use Tests\Fixture\ClassDataProvider;

/**
 * Assertion examples.
 */
final class AsserTest
{
    #[Test]
    public function simpleAssertions(): void
    {
        Assert::same(1, 1);
        Assert::null(null);
        Assert::notSame(42, '42');
        Assert::true(true);
        Assert::false(false);
        Assert::contains(1, [1, 2, 3]);
        Assert::contains(2, new \ArrayIterator([1, 2, 3]));
        Assert::instanceOf(\Exception::class, new \RuntimeException());
        Assert::equals(1, '1');
        Assert::notEquals(42, 43);
    }

    #[Test]
    public function failed(): void
    {
        Assert::same(1, 1, 'One is one btw.');
        Assert::null(null, 'Custom message on null assertion failure.');
        Assert::notSame(42, '42');
        Assert::same(0, null);
    }

    #[Test]
    public function withPrevious(): void
    {
        $nested = new \RuntimeException('Inner exception');
        $e = new \InvalidArgumentException('Outer exception', 42, $nested);
        throw new \RuntimeException('Test exception with previous', 69, $e);
    }

    #[Test]
    #[RetryPolicy(maxAttempts: 2)]
    public function flaky(): void
    {
        static $attempt = 0;
        ++$attempt;
        Assert::same($attempt, 2);
    }

    #[Test]
    public function risky(): void
    {
        // No assertions here
    }

    #[Test]
    public function expectException(): never
    {
        Expect::exception(\RuntimeException::class);

        throw new \RuntimeException('This is an expected exception.');
    }

    #[Test]
    public function expectExceptionObject(): never
    {
        $e = new \RuntimeException('This is an expected exception.');

        Expect::exception($e);

        throw $e;
    }

    #[Test]
    #[ExpectException(\RuntimeException::class)]
    public function expectExceptionAttribute(): never
    {
        throw new \RuntimeException('This is an expected exception.');
    }

    #[Test(description: 'Data provider example')]
    #[DataProvider([self::class, 'dataForProvider'])]
    #[DataSet(['zero'])]
    #[DataSet(['first'])]
    #[DataSet(['second'])]
    public function dataProvider(string $arg): string
    {
        return $arg === 'zero' ? throw new \RuntimeException() : $arg;
    }

    #[Test(description: 'Invokable Class as a Data Provider example')]
    #[DataProvider(new ClassDataProvider())]
    public function classDataProvider(string $val, mixed $eq): void
    {
        Assert::equals($val, $eq);
    }

    public static function dataForProvider(): iterable
    {
        yield ['zero'];
        yield 1 => ['first-1'];
        yield 1 => ['first-2'];
        yield 1 => ['first-3'];
        yield 1 => ['first-4'];
        yield ['second'];
        yield 'name' => ['third'];
        yield 'name' => ['conflict'];
        yield 'Any warrior can change the world.' => ['yep'];
    }

    #[Test]
    public function failWithAnyMessage(): void
    {
        Assert::fail('Any message works here');
    }

    #[Test]
    public function failButCaughtExceptionShouldBeRisky(): void
    {
        try {
            // Assert::fail() sets expectation and throws
            Assert::fail('This exception will be caught');
        } catch (AssertException $e) {
            // Catching the exception prevents the test from failing
            // But the expectation was set, so this should be marked as Risky
        }

        // Test completes successfully despite Assert::fail() being called
    }
}
