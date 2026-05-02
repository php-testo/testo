<?php

declare(strict_types=1);

namespace Tests\Assert\Feature;

use Testo\Assert;
use Testo\Assert\State\Assertion;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Traits\TestRunner;
use Tests\Assert\Stub\AssertJsonNegative;

#[TestingSuite(path: __DIR__ . '/../Stub')]
final class AssertJsonTest
{
    #[Test]
    public function invalidJson(): void
    {
        $result = TestRunner::runTest([AssertJsonNegative::class, 'invalidJson']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Assertion::class);
        Assert::string($result->failure->getFailReason())
            ->contains('got');
    }

    #[Test]
    public function isObjectOnArray(): void
    {
        $result = TestRunner::runTest([AssertJsonNegative::class, 'isObjectOnArray']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Assertion::class);
        Assert::string($result->failure->getFailReason())
            ->contains('got array');
    }

    #[Test]
    public function isArrayOnObject(): void
    {
        $result = TestRunner::runTest([AssertJsonNegative::class, 'isArrayOnObject']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Assertion::class);
        Assert::string($result->failure->getFailReason())
            ->contains('got object');
    }

    #[Test]
    public function isPrimitiveOnObject(): void
    {
        $result = TestRunner::runTest([AssertJsonNegative::class, 'isPrimitiveOnObject']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Assertion::class);
        Assert::string($result->failure->getFailReason())
            ->contains('got object');
    }

    #[Test]
    public function emptyOnNonEmpty(): void
    {
        $result = TestRunner::runTest([AssertJsonNegative::class, 'emptyOnNonEmpty']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Assertion::class);
        Assert::string($result->failure->getFailReason())
            ->contains('3 element');
    }

    #[Test]
    public function wrongCount(): void
    {
        $result = TestRunner::runTest([AssertJsonNegative::class, 'wrongCount']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Assertion::class);
        Assert::string($result->failure->getFailReason())
            ->contains('got 3');
    }

    #[Test]
    public function missingKeys(): void
    {
        $result = TestRunner::runTest([AssertJsonNegative::class, 'missingKeys']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Assertion::class);
        Assert::string($result->failure->getFailReason())
            ->contains('missing key')
            ->contains('`name`')
            ->contains('`email`');
    }

    #[Test]
    public function exceedsMaxDepth(): void
    {
        $result = TestRunner::runTest([AssertJsonNegative::class, 'exceedsMaxDepth']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Assertion::class);
        Assert::string($result->failure->getFailReason())
            ->contains('actual depth is 3');
    }

    #[Test]
    public function wrongMatchesType(): void
    {
        $result = TestRunner::runTest([AssertJsonNegative::class, 'wrongMatchesType']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Assertion::class);
        Assert::string($result->failure->getFailReason())
            ->contains('got 42');
    }

    #[Test]
    public function isStructureOnPrimitive(): void
    {
        $result = TestRunner::runTest([AssertJsonNegative::class, 'isStructureOnPrimitive']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Assertion::class);
        Assert::string($result->failure->getFailReason())
            ->contains('got int');
    }
}
