<?php

declare(strict_types=1);

namespace Tests\Assert\Feature;

use Testo\Assert;
use Testo\Assert\Internal\Expectation\ExpectExceptionHandler;
use Testo\Assert\State\Expectation;
use Testo\Codecov\Covers;
use Testo\Core\Value\Status;
use Testo\Expect;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Assert\Stub\ExpectExceptionNegative;

/**
 * Negative scenarios for {@see Expect::exception()} matching, observed through the runner: each
 * stub fails its expectation, and the rendered fail reason is checked.
 *
 * @see Expect::exception()
 */
#[Test]
#[Covers(Expect::class, 'exception')]
#[Covers(ExpectExceptionHandler::class)]
#[TestingSuite(path: __DIR__ . '/../Stub')]
final class ExpectExceptionNegativeTest
{
    public function noneThrown(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'noneThrown']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('none thrown');
    }

    public function wrongType(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'wrongType']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('got LogicException');
    }

    public function wrongMessage(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'wrongMessage']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('message is "expected message"')
            ->contains('got "actual message"');
    }

    public function failureMessageCarriesTheReason(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'wrongMessage']);

        Assert::string($result->failure?->getMessage())
            ->contains('Failed expectation that exception of type')
            ->contains('Reason: message is "expected message", but got "actual message"');
    }

    public function failureMessageListsEveryReason(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'messageAndCodeMismatch']);

        Assert::string($result->failure?->getMessage())
            ->contains("Reasons:\n- message is")
            ->contains("\n- code is 42, but got 99");
    }

    public function wrongMessagePattern(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'wrongMessagePattern']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('message matches pattern')
            ->contains('not an exact match');
    }

    public function invalidMessagePatternIsAnError(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'invalidMessagePattern']);

        Assert::same($result->status, Status::Error);
        Assert::instanceOf($result->failure, \InvalidArgumentException::class);
        Assert::string($result->failure->getMessage())
            ->contains('Invalid pattern /(/')
            ->contains('missing closing parenthesis');
    }

    public function wrongMessageContaining(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'wrongMessageContaining']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('message contains "needle"');
    }

    public function wrongCode(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'wrongCode']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('code is 42')
            ->contains('got 99');
    }

    public function wrongCodeArray(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'wrongCodeArray']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('code is one of [1, 2, 3]')
            ->contains('got 99');
    }

    public function withoutPreviousButHasOne(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'withoutPreviousButHasOne']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('has no previous exception')
            ->contains('got LogicException');
    }

    public function wrongPreviousType(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'wrongPreviousType']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('has previous exception of type')
            ->contains('got LogicException');
    }

    public function previousCallbackFails(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'previousCallbackFails']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('message is "expected previous message"')
            ->contains('code is 100');
    }

    public function equivalenceWrongMessage(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'equivalenceWrongMessage']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('message is "expected"')
            ->contains('got "actual"');
    }

    public function equivalenceWrongCode(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'equivalenceWrongCode']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('code is 42')
            ->contains('got 99');
    }

    public function sameDifferentInstance(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'sameDifferentInstance']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('the same RuntimeException instance is thrown')
            ->contains('got a different RuntimeException instance');
    }

    public function sameWrongClass(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'sameWrongClass']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('the same RuntimeException instance is thrown')
            ->contains('got LogicException');
    }

    public function strictClassRejectsSubclass(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'strictClassRejectsSubclass']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('exactly RuntimeException is thrown')
            ->contains('subclass of RuntimeException');
    }
}
