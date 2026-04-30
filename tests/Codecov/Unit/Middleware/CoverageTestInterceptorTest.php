<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Middleware;

use Testo\Assert;
use Testo\Codecov\Internal\Middleware\CoverageTestInterceptor;
use Testo\Codecov\Result\CoverageResult;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;
use Testo\Expect;
use Testo\Test;
use Tests\Codecov\Stub\ChildOverridesMethod;
use Tests\Codecov\Stub\ChildOverridesWithCovers;
use Tests\Codecov\Stub\ChildWithoutAttribute;
use Tests\Codecov\Stub\ConflictingAttributes;
use Tests\Codecov\Stub\CoveredCase;
use Tests\Codecov\Stub\SpyDriver;
use Tests\Codecov\Stub\TargetClassA;
use Tests\Codecov\Stub\UncoveredClass;
use Tests\Codecov\Stub\UncoveredMethod;

#[Test]
final class CoverageTestInterceptorTest
{
    public function collectsCoverageForRegularTest(): void
    {
        // Arrange
        $driver = new SpyDriver();
        $interceptor = new CoverageTestInterceptor($driver);
        $info = self::makeTestInfo(CoveredCase::class, 'testSomething');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert
        Assert::same($driver->startCount, 1);
        Assert::same($driver->collectCount, 1);
        Assert::instanceOf($result->getAttribute(CoverageResult::class), CoverageResult::class);
    }

    public function skipsCollectionForCoversNothingOnMethod(): void
    {
        // Arrange
        $driver = new SpyDriver();
        $interceptor = new CoverageTestInterceptor($driver);
        $info = self::makeTestInfo(UncoveredMethod::class, 'testIgnored');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert
        Assert::same($driver->startCount, 0);
        Assert::same($driver->collectCount, 0);
        Assert::null($result->getAttribute(CoverageResult::class));
    }

    public function collectsForMethodWithoutAttribute(): void
    {
        // Arrange
        $driver = new SpyDriver();
        $interceptor = new CoverageTestInterceptor($driver);
        $info = self::makeTestInfo(UncoveredMethod::class, 'testCovered');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert
        Assert::same($driver->startCount, 1);
        Assert::same($driver->collectCount, 1);
    }

    public function skipsCollectionForCoversNothingOnClass(): void
    {
        // Arrange
        $driver = new SpyDriver();
        $interceptor = new CoverageTestInterceptor($driver);
        $info = self::makeTestInfo(UncoveredClass::class, 'testA');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert
        Assert::same($driver->startCount, 0);
        Assert::same($driver->collectCount, 0);
        Assert::null($result->getAttribute(CoverageResult::class));
    }

    public function skipsInheritedMethodWhenBaseHasCoversNothing(): void
    {
        // Arrange
        $driver = new SpyDriver();
        $interceptor = new CoverageTestInterceptor($driver);
        $info = self::makeTestInfo(ChildWithoutAttribute::class, 'testInherited');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert — method is declared in the base class which has CoversNothing
        Assert::same($driver->startCount, 0);
        Assert::same($driver->collectCount, 0);
        Assert::null($result->getAttribute(CoverageResult::class));
    }

    public function skipsOwnMethodOnChildWhenParentHasCoversNothing(): void
    {
        // Arrange
        $driver = new SpyDriver();
        $interceptor = new CoverageTestInterceptor($driver);
        $info = self::makeTestInfo(ChildWithoutAttribute::class, 'testOwn');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert — child has no attribute, but parent class does → skip
        Assert::same($driver->startCount, 0);
        Assert::same($driver->collectCount, 0);
        Assert::null($result->getAttribute(CoverageResult::class));
    }

    public function skipsOverriddenMethodWhenBaseMethodHasCoversNothing(): void
    {
        // Arrange
        $driver = new SpyDriver();
        $interceptor = new CoverageTestInterceptor($driver);
        $info = self::makeTestInfo(ChildOverridesMethod::class, 'testMarkedInBase');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert — child overrides the method, but prototype in parent has the attribute → skip
        Assert::same($driver->startCount, 0);
        Assert::same($driver->collectCount, 0);
        Assert::null($result->getAttribute(CoverageResult::class));
    }

    public function childCoversOverridesParentCoversNothing(): void
    {
        // Arrange — parent has #[CoversNothing], child has #[Covers(TargetClassA::class)]
        $refA = new \ReflectionClass(TargetClassA::class);
        $fileA = $refA->getFileName();

        $driver = new SpyDriver(new CoverageResult([
            $fileA => new \Testo\Codecov\Result\FileCoverage($fileA, [
                $refA->getStartLine() => new \Testo\Codecov\Result\LineCoverage(
                    $refA->getStartLine(),
                    \Testo\Codecov\Result\LineStatus::Executed,
                ),
            ]),
            '/src/Other.php' => new \Testo\Codecov\Result\FileCoverage('/src/Other.php', [
                5 => new \Testo\Codecov\Result\LineCoverage(5, \Testo\Codecov\Result\LineStatus::Executed),
            ]),
        ]));
        $interceptor = new CoverageTestInterceptor($driver);
        $info = self::makeTestInfo(ChildOverridesWithCovers::class, 'testWithCovers');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert — coverage IS collected (child's #[Covers] overrides parent's #[CoversNothing])
        Assert::same($driver->startCount, 1);
        Assert::same($driver->collectCount, 1);

        $coverage = $result->getAttribute(CoverageResult::class);
        Assert::instanceOf($coverage, CoverageResult::class);
        // Filtered to TargetClassA only
        Assert::count($coverage->files, 1);
        Assert::true(isset($coverage->files[$fileA]));
    }

    public function throwsOnConflictingCoversAndCoversNothing(): void
    {
        // Arrange
        $driver = new SpyDriver();
        $interceptor = new CoverageTestInterceptor($driver);
        $info = self::makeTestInfo(ConflictingAttributes::class, 'testConflictOnMethod');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        // Assert
        Expect::exception(\LogicException::class);

        // Act
        $interceptor->runTest($info, $next);
    }

    public function skipsTestTypeNotInAllowList(): void
    {
        // Arrange — interceptor only allows 'test' type
        $driver = new SpyDriver();
        $interceptor = new CoverageTestInterceptor($driver, ['test']);
        $info = self::makeTestInfo(CoveredCase::class, 'testSomething', type: 'bench');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert — driver not started
        Assert::same($driver->startCount, 0);
        Assert::null($result->getAttribute(CoverageResult::class));
    }

    public function collectsWhenTestTypeInAllowList(): void
    {
        // Arrange
        $driver = new SpyDriver();
        $interceptor = new CoverageTestInterceptor($driver, ['test', 'inline']);
        $info = self::makeTestInfo(CoveredCase::class, 'testSomething', type: 'inline');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert
        Assert::same($driver->startCount, 1);
        Assert::instanceOf($result->getAttribute(CoverageResult::class), CoverageResult::class);
    }

    public function emptyTestTypesCollectsAll(): void
    {
        // Arrange — empty list = all types
        $driver = new SpyDriver();
        $interceptor = new CoverageTestInterceptor($driver, []);
        $info = self::makeTestInfo(CoveredCase::class, 'testSomething', type: 'bench');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert
        Assert::same($driver->startCount, 1);
        Assert::instanceOf($result->getAttribute(CoverageResult::class), CoverageResult::class);
    }

    /**
     * @param class-string $class
     * @param non-empty-string $method
     * @param non-empty-string $type
     */
    private static function makeTestInfo(string $class, string $method, string $type = 'test'): TestInfo
    {
        $reflection = new \ReflectionMethod($class, $method);

        return new TestInfo(
            name: "{$class}::{$method}",
            caseInfo: new CaseInfo(
                definition: new CaseDefinition(
                    name: $class,
                    type: $type,
                    reflection: new \ReflectionClass($class),
                ),
            ),
            testDefinition: new TestDefinition($reflection),
        );
    }
}
