<?php

declare(strict_types=1);

namespace Tests\Assert\Stub;

use Testo\Assert;
use Testo\Expect;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Test;

final class EmptyAssertionDescription extends AssertionException
{
    #[\Override]
    public function getAssertion(): string
    {
        return '';
    }
}

final class CustomAssertions
{
    public static ?AssertionException $caughtFailure = null;
    #[Test]
    public function success(): void
    {
        Assert::success(['ok' => true], 'is valid', 'domain check');
    }

    #[Test]
    public function mixedHistory(): void
    {
        Assert::same(1, 1);
        Assert::success('value', 'is custom');
        Assert::true(true);
    }

    #[Test]
    public function uncaughtFailure(): never
    {
        Assert::failure(new AssertionException('value', 'is valid', 'domain check', 'invalid', 'details'));
    }

    #[Test]
    public function caughtFailure(): void
    {
        $failure = new AssertionException('value', 'is valid', 'domain check', 'invalid', 'details');
        try {
            Assert::failure($failure);
        } catch (AssertionException $actual) {
            self::$caughtFailure = $actual;
            if ($actual !== $failure) {
                throw new \LogicException('Failure identity was not preserved');
            }
        }
    }

    #[Test]
    public function expectedComparisonFailure(): never
    {
        Expect::exception(AssertionException::class);
        Assert::failure(new ComparisonFailure(
            expected: 'expected',
            actual: 'actual',
            value: 'actual',
            assertion: 'equals expected',
            context: 'comparison',
            reason: 'different',
            details: 'diff',
        ));
    }

    #[Test]
    public function emptySuccessDescriptionIsRejected(): never
    {
        Expect::exception(\InvalidArgumentException::class);
        Assert::success(1, '');
    }

    #[Test]
    public function emptyAssertionExceptionDescriptionIsRejected(): never
    {
        Expect::exception(\InvalidArgumentException::class);
        new AssertionException('value', '', '', 'reason', '');
    }

    #[Test]
    public function unsupportedThrowableIsRejected(): never
    {
        /** @var mixed $failure */
        $failure = new \RuntimeException('unsupported');
        Expect::exception(\TypeError::class);
        Assert::failure($failure);
    }

    #[Test]
    public function emptyCustomAssertionDescriptionIsRejected(): never
    {
        Expect::exception(\InvalidArgumentException::class);
        Assert::failure(new EmptyAssertionDescription('value', 'valid', '', 'reason', ''));
    }

    #[Test]
    public function validSuccessAfterValidation(): void
    {
        Assert::success(1, 'after validation');
    }
}
