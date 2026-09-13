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
use Testo\ErrorHandler\Exception\ErrorHandlerUnchanged;
use Testo\ErrorHandler\ExpectErrorHandlerChange;
use Testo\ErrorHandler\Internal\ErrorHandlerInterceptor;
use Testo\Test;
use Tests\ErrorHandler\Stub\HandlerChange;
use Tests\ErrorHandler\Stub\HandlerChangeCase;

#[Test]
#[Covers(ErrorHandlerInterceptor::class)]
#[Covers(CapturedError::class)]
#[Covers(CapturedErrors::class)]
#[Covers(ExpectErrorHandlerChange::class)]
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
            }
            \trigger_error('after throw', \E_USER_NOTICE);
        } finally {
            \restore_error_handler();
        }

        Assert::same($count, 1);
    }

    public function restoresTheOuterHandlerWhileSuspendedAndReinstallsItsOwnOnResume(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo();

        $outer = [];
        \set_error_handler(static function (int $severity, string $message) use (&$outer): bool {
            $outer[] = $message;
            return true;
        });

        try {
            $next = static function (TestInfo $info): TestResult {
                \trigger_error('before suspend', \E_USER_NOTICE);
                \Fiber::suspend();
                \trigger_error('after resume', \E_USER_NOTICE);
                return new TestResult(info: $info, status: Status::Passed);
            };

            $fiber = new \Fiber(static fn(): TestResult => $interceptor->runTest($info, $next));
            $fiber->start();

            // Fired while the test is suspended: reaches the outer handler directly, not via the test.
            \trigger_error('fired while suspended', \E_USER_NOTICE);
            Assert::same($outer, ['before suspend', 'fired while suspended']);

            $fiber->resume();
            Assert::true($fiber->isTerminated());

            $result = $fiber->getReturn();
            $errors = $result->getAttribute(CapturedErrors::class);
            Assert::instanceOf($errors, CapturedErrors::class);
            Assert::same(\count($errors->errors), 2);
            Assert::same($errors->errors[0]->message, 'before suspend');
            Assert::same($errors->errors[1]->message, 'after resume');
            Assert::same($outer, ['before suspend', 'fired while suspended', 'after resume']);
        } finally {
            \restore_error_handler();
        }
    }

    public function silencedErrorIsNotCaptured(): void
    {
        $interceptor = new ErrorHandlerInterceptor(failOnError: true);
        $info = self::createTestInfo();
        $next = static function (TestInfo $info): TestResult {
            @\trigger_error('silenced', \E_USER_WARNING);
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($result->status, Status::Passed);
        Assert::null($result->getAttribute(CapturedErrors::class));
    }

    public function errorOutsideErrorReportingIsNotCaptured(): void
    {
        $interceptor = new ErrorHandlerInterceptor(failOnError: true);
        $info = self::createTestInfo();
        $next = static function (TestInfo $info): TestResult {
            \trigger_error('deprecated', \E_USER_DEPRECATED);
            return new TestResult(info: $info, status: Status::Passed);
        };

        $level = \error_reporting(\E_ALL & ~\E_USER_DEPRECATED);
        try {
            $result = $interceptor->runTest($info, $next);
        } finally {
            \error_reporting($level);
        }

        Assert::same($result->status, Status::Passed);
        Assert::null($result->getAttribute(CapturedErrors::class));
    }

    public function handlerLeftByTestDoesNotShadowOuterHandler(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo();
        $next = static function (TestInfo $info): TestResult {
            \set_error_handler(static fn(): bool => true);
            return new TestResult(info: $info, status: Status::Passed);
        };

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

    public function handlerLeftByTestDoesNotCaptureSiblingErrorsWhileSuspended(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo();
        $next = static function (TestInfo $info): TestResult {
            \set_error_handler(static fn(): bool => true);
            \Fiber::suspend();
            return new TestResult(info: $info, status: Status::Passed);
        };

        $outerCount = 0;
        \set_error_handler(static function () use (&$outerCount): bool {
            $outerCount++;
            return true;
        });

        try {
            $fiber = new \Fiber(static fn(): TestResult => $interceptor->runTest($info, $next));
            $fiber->start();

            \trigger_error('fired while suspended', \E_USER_NOTICE);
            Assert::same($outerCount, 1);

            $fiber->resume();
            Assert::null($fiber->getReturn()->getAttribute(CapturedErrors::class));
        } finally {
            \restore_error_handler();
        }
    }

    public function previousHandlerStillReceivesCapturedErrors(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo();
        $next = static function (TestInfo $info): TestResult {
            \trigger_error('forwarded', \E_USER_WARNING);
            return new TestResult(info: $info, status: Status::Passed);
        };

        $seen = [];
        \set_error_handler(static function (int $severity, string $message) use (&$seen): bool {
            $seen[] = $message;
            return true;
        });

        try {
            $result = $interceptor->runTest($info, $next);
        } finally {
            \restore_error_handler();
        }

        Assert::same($seen, ['forwarded']);
        $errors = $result->getAttribute(CapturedErrors::class);
        Assert::instanceOf($errors, CapturedErrors::class);
        Assert::same($errors->errors[0]->message, 'forwarded');
    }

    public function previousHandlerTurningErrorIntoExceptionLeavesNothingCaptured(): void
    {
        $interceptor = new ErrorHandlerInterceptor(failOnError: true);
        $info = self::createTestInfo();
        $next = static function (TestInfo $info): TestResult {
            try {
                \trigger_error('becomes exception', \E_USER_WARNING);
            } catch (\ErrorException) {
                return new TestResult(info: $info, status: Status::Passed);
            }

            return new TestResult(info: $info, status: Status::Failed, failure: new \RuntimeException('not thrown'));
        };

        \set_error_handler(static fn(int $severity, string $message): bool => throw new \ErrorException($message, 0, $severity));

        try {
            $result = $interceptor->runTest($info, $next);
        } finally {
            \restore_error_handler();
        }

        Assert::same($result->status, Status::Passed);
        Assert::null($result->getAttribute(CapturedErrors::class));
    }

    public function previousHandlerObservesTheRealErrorReportingLevel(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo();
        $next = static function (TestInfo $info): TestResult {
            \trigger_error('probe', \E_USER_WARNING);
            return new TestResult(info: $info, status: Status::Passed);
        };

        $observed = null;
        \set_error_handler(static function () use (&$observed): bool {
            $observed = \error_reporting();
            return true;
        });

        $level = \error_reporting(\E_ALL & ~\E_NOTICE);
        try {
            $interceptor->runTest($info, $next);
        } finally {
            \error_reporting($level);
            \restore_error_handler();
        }

        Assert::same($observed, \E_ALL & ~\E_NOTICE);
    }

    public function handlerLeftByTestMarksPassingTestRisky(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo();
        $next = static function (TestInfo $info): TestResult {
            \set_error_handler(static fn(): bool => true);
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($result->status, Status::Risky);
        Assert::false($result->messages->isEmpty());
    }

    public function handlerLeftByTestDoesNotOverrideFailedStatus(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo();
        $failure = new \RuntimeException('assertion failure');
        $next = static function (TestInfo $info) use ($failure): TestResult {
            \set_error_handler(static fn(): bool => true);
            return new TestResult(info: $info, status: Status::Failed, failure: $failure);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($result->status, Status::Failed);
        Assert::same($result->failure, $failure);
    }

    public function handlerRemovedByTestMarksPassingTestRiskyAndKeepsOuterHandler(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo();
        $next = static function (TestInfo $info): TestResult {
            \restore_error_handler();
            return new TestResult(info: $info, status: Status::Passed);
        };

        $count = 0;
        \set_error_handler(static function () use (&$count): bool {
            $count++;
            return true;
        });

        try {
            $result = $interceptor->runTest($info, $next);
            \trigger_error('after test', \E_USER_NOTICE);
        } finally {
            \restore_error_handler();
        }

        Assert::same($result->status, Status::Risky);
        Assert::same($count, 1);
    }

    public function declaredHandlerChangeKeepsPassedStatus(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo(new \ReflectionMethod(HandlerChange::class, 'declared'));
        $next = static function (TestInfo $info): TestResult {
            \set_error_handler(static fn(): bool => true);
            return new TestResult(info: $info, status: Status::Passed);
        };

        $count = 0;
        \set_error_handler(static function () use (&$count): bool {
            $count++;
            return true;
        });

        try {
            $result = $interceptor->runTest($info, $next);
            \trigger_error('after test', \E_USER_NOTICE);
        } finally {
            \restore_error_handler();
        }

        Assert::same($result->status, Status::Passed);
        Assert::same($count, 1);
    }

    public function declaredHandlerChangeOnClassAppliesToItsTests(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo(new \ReflectionMethod(HandlerChangeCase::class, 'inherited'));
        $next = static function (TestInfo $info): TestResult {
            \set_error_handler(static fn(): bool => true);
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($result->status, Status::Passed);
    }

    public function declaredHandlerChangeThatDoesNotHappenFails(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo(new \ReflectionMethod(HandlerChange::class, 'declared'));
        $next = static fn(TestInfo $info): TestResult => new TestResult(info: $info, status: Status::Passed);

        $result = $interceptor->runTest($info, $next);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, ErrorHandlerUnchanged::class);
    }

    public function undeclaredStubMethodIsHeldToThePlainContract(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo(new \ReflectionMethod(HandlerChange::class, 'undeclared'));
        $next = static function (TestInfo $info): TestResult {
            \set_error_handler(static fn(): bool => true);
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest($info, $next);

        Assert::same($result->status, Status::Risky);
    }

    public function handlerInstalledByTestIsBackAfterResume(): void
    {
        $interceptor = new ErrorHandlerInterceptor();
        $info = self::createTestInfo(new \ReflectionMethod(HandlerChange::class, 'declared'));

        $ownCount = 0;
        $next = static function (TestInfo $info) use (&$ownCount): TestResult {
            \set_error_handler(static function () use (&$ownCount): bool {
                $ownCount++;
                return true;
            });
            \Fiber::suspend();
            \trigger_error('after resume', \E_USER_NOTICE);
            return new TestResult(info: $info, status: Status::Passed);
        };

        $outerCount = 0;
        \set_error_handler(static function () use (&$outerCount): bool {
            $outerCount++;
            return true;
        });

        try {
            $fiber = new \Fiber(static fn(): TestResult => $interceptor->runTest($info, $next));
            $fiber->start();

            \trigger_error('fired while suspended', \E_USER_NOTICE);
            Assert::same($outerCount, 1);
            Assert::same($ownCount, 0);

            $fiber->resume();
            Assert::same($ownCount, 1);
            Assert::same($fiber->getReturn()->status, Status::Passed);
            Assert::null($fiber->getReturn()->getAttribute(CapturedErrors::class));

            \trigger_error('after test', \E_USER_NOTICE);
            Assert::same($outerCount, 2);
        } finally {
            \restore_error_handler();
        }
    }

    private static function createTestInfo(?\ReflectionMethod $reflection = null): TestInfo
    {
        $reflection ??= new \ReflectionMethod(self::class, 'createTestInfo');
        $caseDefinition = new CaseDefinition(
            name: 'TestCase',
            type: 'test',
            file: Path::create(__FILE__),
            reflection: $reflection->getDeclaringClass(),
        );
        $caseInfo = new CaseInfo(definition: $caseDefinition, suiteIdentity: new SuiteIdentity('ErrorHandler/Unit'));
        $testDefinition = new TestDefinition(reflection: $reflection);

        return new TestInfo(
            name: 'testMethod',
            caseInfo: $caseInfo,
            testDefinition: $testDefinition,
        );
    }
}
