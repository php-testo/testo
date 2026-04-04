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
use Testo\Test;
use Tests\Codecov\Stub\CoveredCase;
use Tests\Codecov\Stub\SpyDriver;
use Tests\Codecov\Stub\ChildOverridesMethod;
use Tests\Codecov\Stub\ChildWithoutAttribute;
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

    /**
     * @param class-string $class
     * @param non-empty-string $method
     */
    private static function makeTestInfo(string $class, string $method): TestInfo
    {
        $reflection = new \ReflectionMethod($class, $method);

        return new TestInfo(
            name: "{$class}::{$method}",
            caseInfo: new CaseInfo(
                definition: new CaseDefinition(
                    name: $class,
                    type: 'test',
                    reflection: new \ReflectionClass($class),
                ),
            ),
            testDefinition: new TestDefinition($reflection),
        );
    }
}
