<?php

declare(strict_types=1);

namespace Tests\ErrorHandler\Unit;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;
use Testo\ErrorHandler\CapturedError;
use Testo\ErrorHandler\CapturedErrors;
use Testo\ErrorHandler\Internal\ErrorHandlerInterceptor;
use Testo\Test;

#[Test]
#[Covers(ErrorHandlerInterceptor::class)]
#[Covers(CapturedError::class)]
#[Covers(CapturedErrors::class)]
final class ErrorHandlerInterceptorTest
{
    public function noErrorsPassesResultThrough(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo();
        $next = static fn(TestInfo $info): TestResult => new TestResult(info: $info, status: Status::Passed);

        $result = $interceptor->runTest($info, $next);

        Assert::same($result->status, Status::Passed);
        Assert::null($result->getAttribute(CapturedErrors::class));
    }

    public function capturedErrorIsStoredAsAttribute(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo();
        $next = static function (TestInfo $info): TestResult {
            \trigger_error('test warning', \E_USER_WARNING);
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($result->status, Status::Passed);
        $errors = $result->getAttribute(CapturedErrors::class);
        Assert::instanceOf($errors, CapturedErrors::class);
        Assert::false($errors->isEmpty());
        Assert::same(\count($errors->errors), 1);
        Assert::same($errors->errors[0]->message, 'test warning');
        Assert::same($errors->errors[0]->severity, \E_USER_WARNING);
    }

    public function multipleErrorsAreAllCaptured(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo();
        $next = static function (TestInfo $info): TestResult {
            \trigger_error('first', \E_USER_NOTICE);
            \trigger_error('second', \E_USER_WARNING);
            \trigger_error('third', \E_USER_DEPRECATED);
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        $errors = $result->getAttribute(CapturedErrors::class);
        Assert::instanceOf($errors, CapturedErrors::class);
        Assert::same(\count($errors->errors), 3);
        Assert::same($errors->errors[0]->message, 'first');
        Assert::same($errors->errors[1]->message, 'second');
        Assert::same($errors->errors[2]->message, 'third');
    }

    public function collectModePreservesPassingStatus(): void
    {
        $interceptor = new ErrorHandlerInterceptor(failOnError: false);
        $info = self::createTestInfo();
        $next = static function (TestInfo $info): TestResult {
            \trigger_error('deprecated usage', \E_USER_DEPRECATED);
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($result->status, Status::Passed);
        Assert::notNull($result->getAttribute(CapturedErrors::class));
    }

    public function failModeUpgradesPassingTestToFailed(): void
    {
        $interceptor = new ErrorHandlerInterceptor(failOnError: true);
        $info = self::createTestInfo();
        $next = static function (TestInfo $info): TestResult {
            \trigger_error('user warning', \E_USER_WARNING);
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, \ErrorException::class);
        Assert::same($result->failure->getMessage(), 'user warning');
        Assert::same($result->failure->getSeverity(), \E_USER_WARNING);
    }

    public function failModeUsesFirstErrorAsFailure(): void
    {
        $interceptor = new ErrorHandlerInterceptor(failOnError: true);
        $info = self::createTestInfo();
        $next = static function (TestInfo $info): TestResult {
            \trigger_error('first error', \E_USER_WARNING);
            \trigger_error('second error', \E_USER_NOTICE);
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, \ErrorException::class);
        Assert::same($result->failure->getMessage(), 'first error');
    }

    public function failModeDoesNotOverrideAlreadyFailedTest(): void
    {
        $interceptor = new ErrorHandlerInterceptor(failOnError: true);
        $info = self::createTestInfo();
        $originalFailure = new \RuntimeException('assertion failure');
        $next = static function (TestInfo $info) use ($originalFailure): TestResult {
            \trigger_error('also an error', \E_USER_WARNING);
            return new TestResult(info: $info, status: Status::Failed, failure: $originalFailure);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($result->status, Status::Failed);
        Assert::same($result->failure, $originalFailure);
    }

    public function failModeDoesNotOverrideErrorStatus(): void
    {
        $interceptor = new ErrorHandlerInterceptor(failOnError: true);
        $info = self::createTestInfo();
        $originalFailure = new \RuntimeException('unexpected throw');
        $next = static function (TestInfo $info) use ($originalFailure): TestResult {
            \trigger_error('also triggered', \E_USER_WARNING);
            return new TestResult(info: $info, status: Status::Error, failure: $originalFailure);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($result->status, Status::Error);
        Assert::same($result->failure, $originalFailure);
    }

    public function handlerIsRestoredAfterTestCompletes(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo();
        $next = static fn(TestInfo $info): TestResult => new TestResult(info: $info, status: Status::Passed);

        // Zero-param closure: PHP discards extra arguments silently, avoiding S1172.
        $count = 0;
        \set_error_handler(static function () use (&$count): bool {
            $count++;
            return true;
        });

        try {
            $interceptor->runTest($info, $next);
            \trigger_error('after test', \E_USER_NOTICE);
        } finally {
            \restore_error_handler();
        }

        Assert::same($count, 1);
    }

    public function handlerIsRestoredEvenWhenTestThrows(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo();
        // Arrow function with no params: throw is a valid expression in PHP 8+.
        $next = static fn(): TestResult => throw new \RuntimeException('unexpected throw');

        $count = 0;
        \set_error_handler(static function () use (&$count): bool {
            $count++;
            return true;
        });

        try {
            try {
                $interceptor->runTest($info, $next);
            } catch (\RuntimeException) {
                // expected
            }
            \trigger_error('after throw', \E_USER_NOTICE);
        } finally {
            \restore_error_handler();
        }

        Assert::same($count, 1);
    }

    /**
     * The error-handler stack is process-global, and Testo can run tests inside fibers with
     * sibling tests interleaving on suspend/resume. A plain install-before/restore-after around
     * $next() would leave this test's handler installed for the entire suspension window, so a
     * sibling's error fired while this test is suspended would wrongly be captured here instead
     * of reaching whatever was active before this test started.
     */
    public function restoresTheOuterHandlerWhileSuspendedAndReinstallsItsOwnOnResume(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo();

        $outerCount = 0;
        \set_error_handler(static function () use (&$outerCount): bool {
            $outerCount++;
            return true;
        });

        try {
            $next = static function (TestInfo $info): TestResult {
                \trigger_error('before suspend', \E_USER_NOTICE);
                \Fiber::suspend();
                \trigger_error('after resume', \E_USER_NOTICE);
                return new TestResult(info: $info, status: Status::Passed);
            };

            // runTest() only takes the fiber-aware branch when a fiber is already active, so
            // drive it inside our own fiber here — exactly how Testo's scheduler runs a test.
            $fiber = new \Fiber(static fn(): TestResult => $interceptor->runTest($info, $next));
            $fiber->start();

            // While this test is suspended, an error fired by anything else running in the
            // process (a sibling test interleaving via the scheduler) must fall through to
            // whatever was active before this test installed its own handler.
            \trigger_error('fired while suspended', \E_USER_NOTICE);
            Assert::same($outerCount, 1);

            $fiber->resume();
            Assert::true($fiber->isTerminated());

            $result = $fiber->getReturn();
            $errors = $result->getAttribute(CapturedErrors::class);
            Assert::instanceOf($errors, CapturedErrors::class);
            Assert::same(\count($errors->errors), 2);
            Assert::same($errors->errors[0]->message, 'before suspend');
            Assert::same($errors->errors[1]->message, 'after resume');
        } finally {
            \restore_error_handler();
        }
    }

    private static function createTestInfo(): TestInfo
    {
        $reflection = new \ReflectionMethod(self::class, 'createTestInfo');
        $caseDefinition = new CaseDefinition(name: 'TestCase', type: 'test', file: Path::create(__FILE__));
        $caseInfo = new CaseInfo(definition: $caseDefinition, suiteIdentity: new SuiteIdentity('ErrorHandler/Unit'));
        $testDefinition = new TestDefinition(reflection: $reflection);

        return new TestInfo(
            name: 'testMethod',
            caseInfo: $caseInfo,
            testDefinition: $testDefinition,
        );
    }
}
