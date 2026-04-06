<?php

declare(strict_types=1);

namespace Tests\Repeat\Feature;

use Testo\Assert;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Traits\TestRunner;
use Tests\Repeat\Stub\RepeatClassLevelStub;
use Tests\Repeat\Stub\RepeatFailingStub;
use Tests\Repeat\Stub\RepeatPassingStub;

#[TestingSuite(path: __DIR__ . '/../Stub')]
final class RepeatFeatureTest
{
    #[Test]
    public function defaultRepeatPasses(): void
    {
        $result = TestRunner::runTest([RepeatPassingStub::class, 'defaultRepeat']);

        Assert::same($result->status, Status::Passed);
    }

    #[Test]
    public function repeatThreeTimesPasses(): void
    {
        $result = TestRunner::runTest([RepeatPassingStub::class, 'repeatThreeTimes']);

        Assert::same($result->status, Status::Passed);
    }

    #[Test]
    public function repeatOncePasses(): void
    {
        $result = TestRunner::runTest([RepeatPassingStub::class, 'repeatOnce']);

        Assert::same($result->status, Status::Passed);
    }

    #[Test]
    public function failsOnSecondIteration(): void
    {
        $result = TestRunner::runTest([RepeatFailingStub::class, 'failsOnSecondIteration']);

        Assert::same($result->status, Status::Failed);
    }

    #[Test]
    public function failsImmediately(): void
    {
        $result = TestRunner::runTest([RepeatFailingStub::class, 'failsImmediately']);

        Assert::same($result->status, Status::Failed);
    }

    #[Test]
    public function classLevelRepeatFirstTest(): void
    {
        $result = TestRunner::runTest([RepeatClassLevelStub::class, 'firstTest']);

        Assert::same($result->status, Status::Passed);
    }

    #[Test]
    public function classLevelRepeatSecondTest(): void
    {
        $result = TestRunner::runTest([RepeatClassLevelStub::class, 'secondTest']);

        Assert::same($result->status, Status::Passed);
    }
}
