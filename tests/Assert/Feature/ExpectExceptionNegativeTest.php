<?php

declare(strict_types=1);

namespace Tests\Assert\Feature;

use Testo\Assert;
use Testo\Assert\State\Expectation;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Traits\TestRunner;
use Tests\Assert\Stub\ExpectExceptionNegative;

#[TestingSuite(path: __DIR__ . '/../Stub')]
final class ExpectExceptionNegativeTest
{
    #[Test]
    public function noneThrown(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'noneThrown']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('none thrown');
    }

    #[Test]
    public function wrongType(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'wrongType']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('got LogicException');
    }

    #[Test]
    public function wrongMessage(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'wrongMessage']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('message is "expected message"')
            ->contains('got "actual message"');
    }

    #[Test]
    public function wrongMessagePattern(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'wrongMessagePattern']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('message matches pattern')
            ->contains('not an exact match');
    }

    #[Test]
    public function wrongMessageContaining(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'wrongMessageContaining']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('message contains "needle"');
    }

    #[Test]
    public function wrongCode(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'wrongCode']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('code is 42')
            ->contains('got 99');
    }

    #[Test]
    public function wrongCodeArray(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'wrongCodeArray']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('code is one of [1, 2, 3]')
            ->contains('got 99');
    }

    #[Test]
    public function withoutPreviousButHasOne(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'withoutPreviousButHasOne']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('has no previous exception')
            ->contains('got LogicException');
    }

    #[Test]
    public function wrongPreviousType(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'wrongPreviousType']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('has previous exception of type')
            ->contains('got LogicException');
    }

    #[Test]
    public function previousCallbackFails(): void
    {
        $result = TestRunner::runTest([ExpectExceptionNegative::class, 'previousCallbackFails']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('message is "expected previous message"')
            ->contains('code is 100');
    }
}
