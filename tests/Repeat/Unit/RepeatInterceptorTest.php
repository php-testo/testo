<?php

declare(strict_types=1);

namespace Tests\Repeat\Unit;

use Testo\Assert;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
use Testo\Repeat;
use Testo\Repeat\Internal\RepeatInterceptor;
use Testo\Test;

#[Test]
final class RepeatInterceptorTest
{
    public function runsTestSpecifiedNumberOfTimes(): void
    {
        // Arrange
        $interceptor = new RepeatInterceptor(new Repeat(times: 3));
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            $callCount++;
            return new TestResult(info: $info, status: Status::Passed);
        };

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert
        Assert::same($callCount, 3);
        Assert::same($result->status, Status::Passed);
    }

    public function defaultRepeatRunsTestTwice(): void
    {
        // Arrange
        $interceptor = new RepeatInterceptor(new Repeat());
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            $callCount++;
            return new TestResult(info: $info, status: Status::Passed);
        };

        // Act
        $interceptor->runTest($info, $next);

        // Assert
        Assert::same($callCount, 2);
    }

    public function singleRepeatRunsOnce(): void
    {
        // Arrange
        $interceptor = new RepeatInterceptor(new Repeat(times: 1));
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            $callCount++;
            return new TestResult(info: $info, status: Status::Passed);
        };

        // Act
        $interceptor->runTest($info, $next);

        // Assert
        Assert::same($callCount, 1);
    }

    public function returnsLastSuccessfulResult(): void
    {
        // Arrange
        $interceptor = new RepeatInterceptor(new Repeat(times: 3));
        $info = self::createTestInfo();
        $iteration = 0;
        $next = static function (TestInfo $info) use (&$iteration): TestResult {
            $iteration++;
            return new TestResult(info: $info, status: Status::Passed, result: $iteration);
        };

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert
        Assert::same($result->result, 3);
    }

    public function stopsOnFailureMidway(): void
    {
        // Arrange
        $interceptor = new RepeatInterceptor(new Repeat(times: 5));
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            $callCount++;
            if ($callCount === 2) {
                return new TestResult(info: $info, status: Status::Failed);
            }
            return new TestResult(info: $info, status: Status::Passed);
        };

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert
        Assert::same($callCount, 2);
        Assert::same($result->status, Status::Failed);
    }

    /**
     * Verifies repeat behavior per status: failure statuses stop the loop, others continue.
     */
    #[DataSet([Status::Passed, false])]
    #[DataSet([Status::Risky, false])]
    #[DataSet([Status::Flaky, false])]
    #[DataSet([Status::Skipped, false])]
    #[DataSet([Status::Cancelled, false])]
    #[DataSet([Status::Aborted, false])]
    #[DataSet([Status::Failed, true])]
    #[DataSet([Status::Error, true])]
    public function statusBehavior(Status $status, bool $stopsLoop): void
    {
        // Arrange
        $interceptor = new RepeatInterceptor(new Repeat(times: 3));
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount, $status): TestResult {
            $callCount++;
            return new TestResult(info: $info, status: $status);
        };

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert
        Assert::same($callCount, $stopsLoop ? 1 : 3);
        Assert::same($result->status, $status);
    }

    public function passesTestInfoToNext(): void
    {
        // Arrange
        $interceptor = new RepeatInterceptor(new Repeat(times: 2));
        $info = self::createTestInfo();
        $receivedInfos = [];
        $next = static function (TestInfo $receivedInfo) use (&$receivedInfos): TestResult {
            $receivedInfos[] = $receivedInfo;
            return new TestResult(info: $receivedInfo, status: Status::Passed);
        };

        // Act
        $interceptor->runTest($info, $next);

        // Assert
        Assert::same(\count($receivedInfos), 2);
        Assert::same($receivedInfos[0], $info);
        Assert::same($receivedInfos[1], $info);
    }

    private static function createTestInfo(): TestInfo
    {
        $reflection = new \ReflectionMethod(self::class, 'createTestInfo');
        $caseDefinition = new CaseDefinition(name: 'TestCase', type: 'test');
        $caseInfo = new CaseInfo(definition: $caseDefinition);
        $testDefinition = new TestDefinition(reflection: $reflection);

        return new TestInfo(
            name: 'testMethod',
            caseInfo: $caseInfo,
            testDefinition: $testDefinition,
        );
    }
}
