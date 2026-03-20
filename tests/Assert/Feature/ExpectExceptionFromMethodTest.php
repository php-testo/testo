<?php

declare(strict_types=1);

namespace Tests\Assert\Feature;

use Testo\Assert;
use Testo\Assert\State\Expectation;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Traits\TestRunner;
use Tests\Assert\Stub\ExceptionThrower;
use Tests\Assert\Stub\ExpectExceptionFromMethod;

#[TestingSuite(path: __DIR__ . '/../Stub')]
final class ExpectExceptionFromMethodTest
{
    #[Test]
    public function wrongClass(): void
    {
        $result = TestRunner::runTest([ExpectExceptionFromMethod::class, 'wrongClass']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains('stdClass::wrongClass()');
    }

    #[Test]
    public function wrongMethod(): void
    {
        $result = TestRunner::runTest([ExpectExceptionFromMethod::class, 'wrongMethod']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains(ExceptionThrower::class . '::nonExistent()');
    }

    #[Test]
    public function multipleOneWrong(): void
    {
        $result = TestRunner::runTest([ExpectExceptionFromMethod::class, 'multipleOneWrong']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains(ExceptionThrower::class . '::nonExistent()');
    }

    #[Test]
    public function methodNotInTrace(): void
    {
        $result = TestRunner::runTest([ExpectExceptionFromMethod::class, 'methodNotInTrace']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, Expectation::class);
        Assert::string($result->failure->getFailReason())
            ->contains(ExceptionThrower::class . '::deepThrow()');
    }
}
