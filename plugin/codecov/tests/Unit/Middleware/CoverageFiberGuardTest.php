<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Middleware;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Internal\Middleware\CoverageTestInterceptor;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\LineStatus;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;
use Testo\Codecov\Covers;
use Testo\Filter\Group;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Test;
use Tests\Codecov\Stub\InterleavedCase;
use Tests\Codecov\Stub\WindowDriver;

/**
 * Per-test coverage under cooperatively scheduled fibers.
 *
 * The tests drive the fibers by hand rather than through Testo's fiber scheduler: the interceptor
 * only relies on `\Fiber`, and a plain round-robin loop reproduces an interleave exactly while
 * keeping this suite independent of the fiber plugin.
 */
#[Test]
#[Covers(CoverageTestInterceptor::class)]
final class CoverageFiberGuardTest
{
    private const FILE_A = '/src/A.php';
    private const FILE_B = '/src/B.php';

    /**
     * Two tests interleaving on one global window must each end up with their own lines: the one that
     * finishes first must not absorb its sibling's, and the one that finishes later must not come back
     * empty.
     */
    #[Group('async')]
    public function banksEachTestsOwnLinesWhenTestsInterleave(): void
    {
        $driver = new WindowDriver();
        $interceptor = new CoverageTestInterceptor($driver);

        $fiberA = self::testFiber($interceptor, $driver, 'testFirst', self::FILE_A, steps: 2);
        $fiberB = self::testFiber($interceptor, $driver, 'testSecond', self::FILE_B, steps: 4);

        self::roundRobin([$fiberA, $fiberB]);

        $coverageA = self::coverageOf($fiberA);
        $coverageB = self::coverageOf($fiberB);

        Assert::same(\array_keys($coverageA->files), [self::FILE_A]);
        Assert::same(\array_keys($coverageB->files), [self::FILE_B]);

        // Each line is attributed to the one test that executed it — this is what `<covered by>` in
        // the report, and Infection's mutant-to-test mapping, are built from.
        Assert::same(
            $coverageA->files[self::FILE_A]->lines[1]->testMethods,
            [InterleavedCase::class . '::testFirst'],
        );
        Assert::same(
            $coverageB->files[self::FILE_B]->lines[1]->testMethods,
            [InterleavedCase::class . '::testSecond'],
        );
    }

    /**
     * Every line a test executed has to survive, however many suspensions split it up: the slices are
     * banked on the way out and merged, not overwritten.
     */
    #[Group('async')]
    public function keepsEveryLineOfAnInterleavedTest(): void
    {
        $driver = new WindowDriver();
        $interceptor = new CoverageTestInterceptor($driver);

        $fiberA = self::testFiber($interceptor, $driver, 'testFirst', self::FILE_A, steps: 3);
        $fiberB = self::testFiber($interceptor, $driver, 'testSecond', self::FILE_B, steps: 3);

        self::roundRobin([$fiberA, $fiberB]);

        $lines = self::coverageOf($fiberA)->files[self::FILE_A]->lines;

        Assert::same(\array_keys($lines), [1, 2, 3]);
        foreach ($lines as $line) {
            Assert::same($line->status, LineStatus::Executed);
        }
    }

    /**
     * The invariant that keeps XDebug alive: with branch analysis on, a window left open across a
     * fiber switch corrupts memory and kills the process. No window may be open while a test is parked.
     */
    #[Group('async')]
    public function leavesNoWindowOpenWhileATestIsParked(): void
    {
        $driver = new WindowDriver();
        $interceptor = new CoverageTestInterceptor($driver);

        $fibers = [
            self::testFiber($interceptor, $driver, 'testFirst', self::FILE_A, steps: 3),
            self::testFiber($interceptor, $driver, 'testSecond', self::FILE_B, steps: 3),
        ];

        $switches = 0;
        self::roundRobin($fibers, static function () use ($driver, &$switches): void {
            Assert::false($driver->open(), 'A coverage window is open while every test is parked.');
            ++$switches;
        });

        // Guard against a vacuous pass: the tests really did yield control back.
        Assert::true($switches > 0);
    }

    /**
     * The trampoline only works through fibers Testo drives itself, so the interceptor has to stay
     * outer to {@see InterceptorOptions::ORDER_ASYNC_COROUTINE} — the slot for interceptors that hand
     * the test to a fiber they own and resume directly (`testo/bridge-revolt` dispatches the body onto
     * the Revolt event loop). Suspending out of such a fiber leaves nobody to resume it and the run
     * dies on "Event loop terminated without resuming the current suspension". Staying inner to
     * {@see InterceptorOptions::ORDER_CLOSE_TO_TEST} keeps the window over as little framework code as
     * possible.
     */
    public function isOrderedOutsideTheAsyncCoroutineSlot(): void
    {
        $options = (new \ReflectionClass(CoverageTestInterceptor::class))
            ->getAttributes(InterceptorOptions::class)[0]
            ->newInstance();

        Assert::same($options->order, InterceptorOptions::ORDER_COVERAGE);
        Assert::true(InterceptorOptions::ORDER_COVERAGE < InterceptorOptions::ORDER_ASYNC_COROUTINE);
        Assert::true(InterceptorOptions::ORDER_COVERAGE > InterceptorOptions::ORDER_CLOSE_TO_TEST);
    }

    /**
     * A test that runs without any fiber keeps the plain single-window path — one `start()`, one
     * `collect()`, no trampoline.
     */
    public function collectsInOneWindowWithoutFibers(): void
    {
        $driver = new WindowDriver();
        $interceptor = new CoverageTestInterceptor($driver);

        $result = $interceptor->runTest(
            self::makeTestInfo('testFirst'),
            static function (TestInfo $info) use ($driver): TestResult {
                $driver->touch(self::FILE_A, 1);

                return new TestResult($info, Status::Passed);
            },
        );

        $coverage = $result->getAttribute(CoverageResult::class);
        Assert::instanceOf($coverage, CoverageResult::class);
        Assert::same(\array_keys($coverage->files), [self::FILE_A]);
        Assert::false($driver->open());
    }

    /**
     * One scheduled test: a fiber running the interceptor, whose body touches `$steps` lines of
     * `$file` and suspends after each — the shape of a test that yields mid-run.
     *
     * @param non-empty-string $name
     * @param non-empty-string $file
     * @param int<1, max> $steps
     */
    private static function testFiber(
        CoverageTestInterceptor $interceptor,
        WindowDriver $driver,
        string $name,
        string $file,
        int $steps,
    ): \Fiber {
        $info = self::makeTestInfo($name);
        $next = static function (TestInfo $info) use ($driver, $file, $steps): TestResult {
            for ($line = 1; $line <= $steps; $line++) {
                $driver->touch($file, $line);
                \Fiber::suspend();
            }

            return new TestResult($info, Status::Passed);
        };

        return new \Fiber(static fn(): TestResult => $interceptor->runTest($info, $next));
    }

    /**
     * Drive the fibers one step each per round until all are done, calling `$atSwitch` after every
     * step — i.e. at each point where a test has yielded and another is about to run.
     *
     * @param list<\Fiber> $fibers
     */
    private static function roundRobin(array $fibers, ?callable $atSwitch = null): void
    {
        do {
            $alive = false;
            foreach ($fibers as $fiber) {
                if ($fiber->isTerminated()) {
                    continue;
                }

                $fiber->isStarted() ? $fiber->resume() : $fiber->start();
                $alive = true;

                $fiber->isTerminated() or $atSwitch === null or $atSwitch();
            }
        } while ($alive);
    }

    private static function coverageOf(\Fiber $fiber): CoverageResult
    {
        /** @var TestResult $result */
        $result = $fiber->getReturn();
        $coverage = $result->getAttribute(CoverageResult::class);

        Assert::instanceOf($coverage, CoverageResult::class);

        return $coverage;
    }

    /**
     * @param non-empty-string $method
     */
    private static function makeTestInfo(string $method): TestInfo
    {
        return new TestInfo(
            name: $method,
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Codecov/Unit'),
                definition: new CaseDefinition(
                    name: InterleavedCase::class,
                    type: 'test',
                    file: Path::create(__FILE__),
                    reflection: new \ReflectionClass(InterleavedCase::class),
                ),
            ),
            testDefinition: new TestDefinition(new \ReflectionMethod(InterleavedCase::class, $method)),
        );
    }
}
