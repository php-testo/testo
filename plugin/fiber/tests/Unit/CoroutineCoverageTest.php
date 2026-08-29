<?php

declare(strict_types=1);

namespace Tests\Fiber\Unit;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Codecov\Internal\Middleware\CoverageTestInterceptor;
use Testo\Codecov\Result\CoverageResult;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;
use Testo\Fiber\Coroutine;
use Testo\Fiber\Internal\CoroutineScopeInterceptor;
use Testo\Fiber\RunInFiber;
use Testo\Filter\Group;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Test;
use Tests\Codecov\Stub\WindowDriver;

/**
 * The coroutine scope sits **inside** the coverage window, so everything a test's coroutines execute
 * is measured for that test. These cases compose the two interceptors by hand — coverage outer, scope
 * inner, the pair driven from a fiber as the `#[RunInFiber]` wrap does — and read what the collector
 * banked off the result.
 */
#[Test]
#[Covers(CoroutineScopeInterceptor::class)]
final class CoroutineCoverageTest
{
    private const FILE_BODY = '/src/Body.php';
    private const FILE_COROUTINE = '/src/Coroutine.php';

    /**
     * Lines a coroutine executes belong to the test that spawned it. Were the scope outer to the
     * collector, the trampoline would close the window at every suspension the body relays — and the
     * coroutines, stepped from outside it, would be measured for nobody.
     */
    #[Group('async')]
    public function coroutineLinesLandInTheSpawningTestsWindow(): void
    {
        $driver = new WindowDriver();

        $coverage = self::drive($driver);

        Assert::same(\array_keys($coverage->files), [self::FILE_BODY, self::FILE_COROUTINE]);
        # Every slice survives the suspensions that split it up, on both sides of the spawn.
        Assert::same(\array_keys($coverage->files[self::FILE_BODY]->lines), [1, 2]);
        Assert::same(\array_keys($coverage->files[self::FILE_COROUTINE]->lines), [1, 2]);
    }

    /**
     * The invariant the collector's trampoline exists for still holds with a scope in the chain: when
     * the scope relays a round to the outer schedule, no window is left open for a sibling test to
     * record into.
     */
    #[Group('async')]
    public function leavesNoWindowOpenWhenTheScopeRelaysOutward(): void
    {
        $driver = new WindowDriver();

        $relays = 0;
        self::drive($driver, static function () use ($driver, &$relays): void {
            Assert::false($driver->open(), 'A coverage window is open while the test is parked.');
            ++$relays;
        });

        # Guard against a vacuous pass: the scope really did hand control outward.
        Assert::true($relays > 0);
    }

    /**
     * The placement contract in one assertion: the scope takes the async-coroutine slot, which is
     * inner to the coverage slot.
     */
    public function isOrderedInsideTheCoverageSlot(): void
    {
        $options = (new \ReflectionClass(CoroutineScopeInterceptor::class))
            ->getAttributes(InterceptorOptions::class)[0]
            ->newInstance();

        Assert::same($options->order, InterceptorOptions::ORDER_ASYNC_COROUTINE);
        Assert::true(InterceptorOptions::ORDER_ASYNC_COROUTINE > InterceptorOptions::ORDER_COVERAGE);
    }

    /**
     * Run one test — body plus a spawned coroutine, each touching two lines around a suspension —
     * through `coverage(scope(body))`, driven from a fiber like the `#[RunInFiber]` wrap drives it.
     * `$atRelay` is called wherever the scope hands control outward.
     */
    private static function drive(WindowDriver $driver, ?callable $atRelay = null): CoverageResult
    {
        $interceptor = new CoverageTestInterceptor($driver);
        $scope = new CoroutineScopeInterceptor(new RunInFiber());

        $body = static function (TestInfo $info) use ($driver): TestResult {
            $driver->touch(self::FILE_BODY, 1);
            Coroutine::spawn(static function () use ($driver): void {
                $driver->touch(self::FILE_COROUTINE, 1);
                \Fiber::suspend();
                $driver->touch(self::FILE_COROUTINE, 2);
            });

            \Fiber::suspend();
            $driver->touch(self::FILE_BODY, 2);

            return new TestResult($info, Status::Passed);
        };

        $info = self::makeTestInfo();
        $fiber = new \Fiber(static fn(): TestResult => $interceptor->runTest(
            $info,
            static fn(TestInfo $i): TestResult => $scope->runTest($i, $body),
        ));

        $fiber->start();
        while (!$fiber->isTerminated()) {
            $atRelay === null or $atRelay();
            $fiber->resume();
        }

        /** @var TestResult $result */
        $result = $fiber->getReturn();
        $coverage = $result->getAttribute(CoverageResult::class);

        Assert::instanceOf($coverage, CoverageResult::class);

        return $coverage;
    }

    private static function makeTestInfo(): TestInfo
    {
        return new TestInfo(
            name: 'scopedTest',
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Fiber/Unit'),
                definition: new CaseDefinition(
                    name: ScopedCase::class,
                    type: 'test',
                    file: Path::create(__FILE__),
                    reflection: new \ReflectionClass(ScopedCase::class),
                ),
            ),
            testDefinition: new TestDefinition(new \ReflectionMethod(ScopedCase::class, 'scopedTest')),
        );
    }
}

/**
 * Case shell the composed interceptors need a reflection of; the behaviour under test lives in the
 * closures {@see CoroutineCoverageTest::drive()} passes down.
 */
final class ScopedCase
{
    public function scopedTest(): void {}
}
