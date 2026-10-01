<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Feature;

use Testo\Assert;
use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Codecov\Covers;
use Testo\Common\Messenger;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Lifecycle\Internal\LifecycleInterceptor;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Lifecycle\Stub\HookFailure\ExpectedExceptionTeardownStub;
use Tests\Lifecycle\Stub\HookFailure\FailedTestTeardownStub;
use Tests\Lifecycle\Stub\HookFailure\PassedTestTeardownStub;
use Tests\Lifecycle\Stub\HookFailure\SetupFailureStub;
use Tests\Lifecycle\Stub\HookFailure\SetupMatchingExpectationStub;

#[Test]
#[Covers(LifecycleInterceptor::class)]
#[TestingSuite(path: __DIR__ . '/../Stub/HookFailure')]
final class HookFailureFeatureTest
{
    public function teardownFailureKeepsTheFailureOfAFailedTest(): void
    {
        $result = TestRunner::runTest([FailedTestTeardownStub::class, 'fails']);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, ComparisonFailure::class);
        Assert::string(self::stderr($result))->contains('teardown of a failed test');
    }

    public function teardownFailureKeepsAPassedTestPassed(): void
    {
        $calls = PassedTestTeardownStub::$afterTestCalls;

        $result = TestRunner::runTest([PassedTestTeardownStub::class, 'passes']);

        Assert::same($result->status, Status::Passed);
        Assert::same(PassedTestTeardownStub::$afterTestCalls - $calls, 2);
        Assert::string(self::stderr($result))
            ->contains('first teardown')
            ->contains('second teardown');
    }

    public function teardownFailureKeepsATestThatMetItsExpectedExceptionPassed(): void
    {
        $result = TestRunner::runTest([ExpectedExceptionTeardownStub::class, 'throwsExpected']);

        Assert::same($result->status, Status::Passed);
        Assert::string(self::stderr($result))->contains('teardown of a test that met its expectation');
    }

    public function setupFailureAbortsTheTestButRunsTheTeardown(): void
    {
        $secondSetUp = SetupFailureStub::$secondSetUpCalls;
        $body = SetupFailureStub::$bodyCalls;
        $afterTest = SetupFailureStub::$afterTestCalls;

        $result = TestRunner::runTest([SetupFailureStub::class, 'body']);

        Assert::same($result->status, Status::Aborted);
        Assert::same($result->failure?->getPrevious()?->getMessage(), 'setup');
        Assert::same(SetupFailureStub::$secondSetUpCalls - $secondSetUp, 0);
        Assert::same(SetupFailureStub::$bodyCalls - $body, 0);
        Assert::same(SetupFailureStub::$afterTestCalls - $afterTest, 1);
    }

    public function setupFailureDoesNotSatisfyAnExpectedException(): void
    {
        $result = TestRunner::runTest([SetupMatchingExpectationStub::class, 'expectsRuntimeException']);

        Assert::same($result->status, Status::Aborted);
        Assert::same($result->failure?->getPrevious()?->getMessage(), 'setup');
    }

    private static function stderr(TestResult $result): string
    {
        return \implode("\n", \array_map(
            static fn($message): string => $message->content,
            $result->messages->channel(Messenger::CHANNEL_STDERR),
        ));
    }
}
