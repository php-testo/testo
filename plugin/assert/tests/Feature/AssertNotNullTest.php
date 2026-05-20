<?php

declare(strict_types=1);

namespace Tests\Assert\Feature;

use Testo\Assert;
use Testo\Assert\State\Assertion;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Traits\TestRunner;
use Tests\Assert\Stub\AssertNotNullNegative;
use Tests\Assert\Stub\AssertNotNullPositive;

#[TestingSuite(path: __DIR__ . '/../Stub')]
final class AssertNotNullTest
{
    #[Test]
    public function nonNullValuePasses(): void
    {
        $result = TestRunner::runTest([AssertNotNullPositive::class, 'falsyNonNullValues']);

        Assert::same($result->status, Status::Passed);
    }

    #[Test]
    public function nullFails(): void
    {
        $result = TestRunner::runTest([AssertNotNullNegative::class, 'nullFails']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Assertion::class);
        Assert::string($result->failure->getFailReason())
            ->contains('expected a non-null value, got `null`');
    }

    #[Test]
    public function nullFailsWithMessage(): void
    {
        $result = TestRunner::runTest([AssertNotNullNegative::class, 'nullFailsWithMessage']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Assertion::class);
        Assert::string($result->failure->getFailReason())
            ->contains('expected a non-null value, got `null`');
        Assert::same($result->failure->getContext(), 'Value must not be null.');
    }
}