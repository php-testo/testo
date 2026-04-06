<?php

declare(strict_types=1);

namespace Tests\Testo\Self;

use Testo\Assert;
use Testo\Assert\ExpectException;
use Testo\Assert\State\AssertException;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Repeat;
use Testo\Retry;
use Testo\Test;
use Tests\Fixture\ClassDataProvider;

/**
 * Assertion examples.
 */
final class AssertTest
{
    #[Test]
    public function simpleAssertions(): void
    {
        Assert::same(1, 1);
        Assert::null(null);
        Assert::notSame('42', 42);
        Assert::true(true);
        Assert::false(false);
        Assert::contains([1, 2, 3], 1);
        Assert::contains(new \ArrayIterator([1, 2, 3]), 2);
        Assert::instanceOf(new \RuntimeException(), \Exception::class);
        Assert::equals('1', 1);
        Assert::notEquals(43, 42);
    }

    #[Test]
    public function failed(): void
    {
        Assert::same(1, 1, 'One is one btw.');
        Assert::null(null, 'Custom message on null assertion failure.');
        Assert::notSame('42', 42);
        Assert::same(null, 0);
    }

    #[Test]
    public function withPrevious(): void
    {
        $nested = new \RuntimeException('Inner exception');
        $e = new \InvalidArgumentException('Outer exception', 42, $nested);
        throw new \RuntimeException('Test exception with previous', 69, $e);
    }

    #[Test]
    #[Repeat()]
    public function repeatSuccess(): void
    {
        static $counter = 0;
        ++$counter;
        $counter > 1 ? Assert::same(2, $counter) : Assert::same(1, $counter);
    }

    #[Test]
    #[Retry(maxAttempts: 3)]
    #[Repeat(times: 3)]
    public function repeatFail(): void
    {
        static $counter = 0;
        ++$counter;
        try {
            Assert::int($counter)->lessThanOrEqual(2);
        } catch (\Throwable $t) {
            $counter = 0;
            throw $t;
        }
    }

    #[Test]
    #[Retry(maxAttempts: 2)]
    public function flaky(): void
    {
        static $attempt = 0;
        ++$attempt;
        Assert::same(2, $attempt);
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

    /**
     * Data provider example
     */
    #[Test]
    #[DataProvider([self::class, 'dataForProvider'])]
    #[DataSet(['zero'])]
    #[DataSet(['first'])]
    #[DataSet(['second'])]
    public function dataProvider(string $arg): string
    {
        return $arg === 'zero' ? throw new \RuntimeException() : $arg;
    }

    /**
     * Invokable Class as a Data Provider example
     */
    #[Test]
    #[DataProvider(new ClassDataProvider())]
    public function classDataProvider(string $val, mixed $eq): void
    {
        Assert::equals($eq, $val);
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
