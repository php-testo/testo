<?php

declare(strict_types=1);

namespace Tests\Repeat\Unit;

use Internal\Path;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Assert;
use Testo\Common\Messenger;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Log\Message;
use Testo\Core\Value\Status;
use Testo\Data\DataSet;
use Testo\Application\Internal\MessengerHub;
use Testo\Codecov\Covers;
use Testo\Repeat;
use Testo\Repeat\Internal\RepeatInterceptor;
use Testo\Test;

#[Test]
#[Covers(RepeatInterceptor::class)]
final class RepeatInterceptorTest
{
    public function runsTestSpecifiedNumberOfTimes(): void
    {
        $interceptor = self::createInterceptor(new Repeat(times: 3));
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            $callCount++;
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($callCount, 3);
        Assert::same($result->status, Status::Passed);
    }

    public function defaultRepeatRunsTestTwice(): void
    {
        $interceptor = self::createInterceptor(new Repeat());
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            $callCount++;
            return new TestResult(info: $info, status: Status::Passed);
        };

        $interceptor->runTest($info, $next);

        Assert::same($callCount, 2);
    }

    public function singleRepeatRunsOnce(): void
    {
        $interceptor = self::createInterceptor(new Repeat(times: 1));
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            $callCount++;
            return new TestResult(info: $info, status: Status::Passed);
        };

        $interceptor->runTest($info, $next);

        Assert::same($callCount, 1);
    }

    public function returnsLastSuccessfulResult(): void
    {
        $interceptor = self::createInterceptor(new Repeat(times: 3));
        $info = self::createTestInfo();
        $iteration = 0;
        $next = static function (TestInfo $info) use (&$iteration): TestResult {
            $iteration++;
            return new TestResult(info: $info, status: Status::Passed, result: $iteration);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($result->result, 3);
    }

    public function stopsOnFailureMidway(): void
    {
        $interceptor = self::createInterceptor(new Repeat(times: 5));
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            $callCount++;
            if ($callCount === 2) {
                return new TestResult(info: $info, status: Status::Failed);
            }
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($callCount, 2);
        Assert::same($result->status, Status::Failed);
    }

    public function toleratesFailuresWithinMaxFailures(): void
    {
        $interceptor = self::createInterceptor(new Repeat(times: 5, maxFailures: 2));
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            $callCount++;
            return new TestResult(
                info: $info,
                status: \in_array($callCount, [2, 4], true) ? Status::Failed : Status::Passed,
            );
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($callCount, 5);
        Assert::same($result->status, Status::Flaky);
    }

    public function failsWhenFailuresExceedMaxFailures(): void
    {
        $interceptor = self::createInterceptor(new Repeat(times: 10, maxFailures: 2));
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            $callCount++;
            return new TestResult(
                info: $info,
                status: $callCount <= 3 ? Status::Failed : Status::Passed,
            );
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($callCount, 3);
        Assert::same($result->status, Status::Failed);
    }

    public function stopsImmediatelyWhenMaxFailuresExceededByErrorStatus(): void
    {
        $interceptor = self::createInterceptor(new Repeat(times: 5, maxFailures: 1));
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            $callCount++;
            return new TestResult(info: $info, status: Status::Error);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($callCount, 2);
        Assert::same($result->status, Status::Error);
    }

    public function staysPassedWhenAllRepetitionsPassEvenWithMaxFailures(): void
    {
        $interceptor = self::createInterceptor(new Repeat(times: 3, maxFailures: 2));
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            $callCount++;
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($callCount, 3);
        Assert::same($result->status, Status::Passed);
    }

    public function failureWithinThresholdDoesNotMarkFlakyWhenDisabled(): void
    {
        $interceptor = self::createInterceptor(
            new Repeat(times: 4, maxFailures: 2, markFlaky: false),
        );
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            $callCount++;
            return new TestResult(
                info: $info,
                status: $callCount === 2 ? Status::Failed : Status::Passed,
            );
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($callCount, 4);
        Assert::same($result->status, Status::Passed);
    }

    public function preservesFailureThrowableWhenMarkingFlaky(): void
    {
        $throwable = new \RuntimeException('boom');
        $interceptor = self::createInterceptor(new Repeat(times: 3, maxFailures: 1));
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount, $throwable): TestResult {
            $callCount++;
            return $callCount === 3
                ? new TestResult(info: $info, status: Status::Failed, failure: $throwable)
                : new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($callCount, 3);
        Assert::same($result->status, Status::Flaky);
        Assert::same($result->failure, $throwable);
    }

    public function skippedAbortsLoopRegardlessOfMaxFailures(): void
    {
        $interceptor = self::createInterceptor(new Repeat(times: 5, maxFailures: 3));
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            $callCount++;
            return new TestResult(info: $info, status: Status::Skipped);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($callCount, 1);
        Assert::same($result->status, Status::Skipped);
    }

    /**
     * Verifies repeat behavior per status: failure statuses stop the loop, others continue.
     */
    #[DataSet([Status::Passed, false])]
    #[DataSet([Status::Risky, false])]
    #[DataSet([Status::Flaky, false])]
    #[DataSet([Status::Skipped, true])]
    #[DataSet([Status::Cancelled, true])]
    #[DataSet([Status::Aborted, true])]
    #[DataSet([Status::Failed, true])]
    #[DataSet([Status::Error, true])]
    public function statusBehavior(Status $status, bool $stopsLoop): void
    {
        $interceptor = self::createInterceptor(new Repeat(times: 3));
        $info = self::createTestInfo();
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount, $status): TestResult {
            $callCount++;
            return new TestResult(info: $info, status: $status);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($callCount, $stopsLoop ? 1 : 3);
        Assert::same($result->status, $status);
    }

    public function passesTestInfoToNext(): void
    {
        $interceptor = self::createInterceptor(new Repeat(times: 2));
        $info = self::createTestInfo();
        $receivedInfos = [];
        $next = static function (TestInfo $receivedInfo) use (&$receivedInfos): TestResult {
            $receivedInfos[] = $receivedInfo;
            return new TestResult(info: $receivedInfo, status: Status::Passed);
        };

        $interceptor->runTest($info, $next);

        Assert::same(\count($receivedInfos), 2);
        Assert::same($receivedInfos[0], $info);
        Assert::same($receivedInfos[1], $info);
    }

    public function recordsAStatusSymbolPerRun(): void
    {
        $messenger = self::createMessenger();
        $interceptor = new RepeatInterceptor(new Repeat(times: 3, maxFailures: 5), $messenger);
        $info = self::createTestInfo();
        $next = static fn(TestInfo $info): TestResult => new TestResult(info: $info, status: Status::Failed);

        $interceptor->runTest($info, $next);

        $symbols = \array_map(
            static fn(Message $m): string => $m->content,
            $messenger->getMessages()->channel(RepeatInterceptor::CHANNEL),
        );
        Assert::same(\implode('', $symbols), "FFF\n");
    }

    private static function createInterceptor(Repeat $options): RepeatInterceptor
    {
        return new RepeatInterceptor($options, self::createMessenger());
    }

    private static function createMessenger(): Messenger
    {
        return new MessengerHub(new class() implements EventDispatcherInterface {
            #[\Override]
            public function dispatch(object $event): object
            {
                return $event;
            }
        });
    }

    private static function createTestInfo(): TestInfo
    {
        $reflection = new \ReflectionMethod(self::class, 'createTestInfo');
        $caseDefinition = new CaseDefinition(name: 'TestCase', type: 'test', file: Path::create(__FILE__));
        $caseInfo = new CaseInfo(suiteIdentity: new SuiteIdentity('Repeat/Unit'), definition: $caseDefinition);
        $testDefinition = new TestDefinition(reflection: $reflection);

        return new TestInfo(
            name: 'testMethod',
            caseInfo: $caseInfo,
            testDefinition: $testDefinition,
        );
    }
}
