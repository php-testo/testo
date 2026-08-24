<?php

declare(strict_types=1);

namespace Tests\Bridge\Revolt\Unit;

use Internal\Path;
use Revolt\EventLoop;
use Testo\Assert;
use Testo\Bridge\Revolt\Internal\RunInRevoltInterceptor;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;
use Testo\Expect;
use Testo\Filter\Group;
use Testo\Test;

/**
 * Unit: {@see RunInRevoltInterceptor::runTest()} puts the test body on the Revolt loop and hands back
 * what it returned — the body runs in a loop fiber, not on `{main}`, and the interceptor blocks until it
 * is done.
 */
#[Test]
#[Covers(RunInRevoltInterceptor::class)]
final class RunInRevoltInterceptorTest
{
    #[Group('async')]
    public function theTestBodyRunsInALoopFiber(): void
    {
        $onLoop = null;
        $next = static function (TestInfo $info) use (&$onLoop): TestResult {
            $onLoop = \Fiber::getCurrent() !== null && EventLoop::getDriver()->isRunning();
            return self::passed($info);
        };

        (new RunInRevoltInterceptor())->runTest(self::testInfo(), $next);

        Assert::true($onLoop);
    }

    public function theResultTravelsBackOutOfTheLoop(): void
    {
        $expected = self::passed(self::testInfo());

        $result = (new RunInRevoltInterceptor())
            ->runTest(self::testInfo(), static fn(): TestResult => $expected);

        Assert::same($result, $expected);
    }

    public function aFailureInsideTheLoopIsRethrownToTheCaller(): void
    {
        // The dispatch relays the throwable out of the loop fiber, so the pipeline above sees the failure
        // where it would have seen it without the loop at all.
        Expect::exception(\RuntimeException::class)->withMessage('boom');

        (new RunInRevoltInterceptor())->runTest(
            self::testInfo(),
            static fn(): TestResult => throw new \RuntimeException('boom'),
        );
    }

    private static function testInfo(): TestInfo
    {
        return new TestInfo(
            name: 'awaitsSomething',
            caseInfo: self::caseInfo(),
            testDefinition: new TestDefinition(
                reflection: new \ReflectionMethod(self::class, 'testInfo'),
            ),
        );
    }

    private static function caseInfo(): CaseInfo
    {
        return new CaseInfo(
            definition: new CaseDefinition(name: 'RevoltCase', type: 'test', file: Path::create(__FILE__)),
            suiteIdentity: new SuiteIdentity('Revolt/Unit'),
        );
    }

    private static function passed(TestInfo $info): TestResult
    {
        return new TestResult(info: $info, status: Status::Passed);
    }
}
