<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Middleware;

use Testo\Assert;
use Testo\Codecov\Internal\Middleware\CoverageTestInterceptor;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\FileCoverage;
use Testo\Codecov\Result\LineCoverage;
use Testo\Codecov\Result\LineStatus;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;
use Testo\Test;
use Tests\Codecov\Stub\CoveredByAttribute;
use Tests\Codecov\Stub\SpyDriver;
use Tests\Codecov\Stub\TargetClassA;
use Tests\Codecov\Stub\TargetClassB;

#[Test]
final class CoversFilteringTest
{
    public function coversClassFiltersToTargetFileOnly(): void
    {
        // Arrange — driver returns coverage for both target files
        $refA = new \ReflectionClass(TargetClassA::class);
        $refB = new \ReflectionClass(TargetClassB::class);
        $fileA = $refA->getFileName();
        $fileB = $refB->getFileName();

        $driver = new SpyDriver(new CoverageResult([
            $fileA => new FileCoverage($fileA, [
                $refA->getStartLine() => new LineCoverage($refA->getStartLine(), LineStatus::Executed),
                $refA->getEndLine() => new LineCoverage($refA->getEndLine(), LineStatus::NotExecuted),
            ]),
            $fileB => new FileCoverage($fileB, [$refB->getStartLine() => new LineCoverage($refB->getStartLine(), LineStatus::Executed)]),
        ]));

        $interceptor = new CoverageTestInterceptor($driver);
        // testCoversA has #[Covers(TargetClassA::class)] — only fileA should survive
        $info = self::makeTestInfo(CoveredByAttribute::class, 'testCoversA');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert
        $coverage = $result->getAttribute(CoverageResult::class);
        Assert::instanceOf($coverage, CoverageResult::class);
        Assert::count($coverage->files, 1);
        Assert::true(isset($coverage->files[$fileA]));
        Assert::false(isset($coverage->files[$fileB]));
    }

    public function multipleCoversKeepsAllTargetFiles(): void
    {
        // Arrange
        $refA = new \ReflectionClass(TargetClassA::class);
        $refB = new \ReflectionClass(TargetClassB::class);
        $fileA = $refA->getFileName();
        $fileB = $refB->getFileName();

        $driver = new SpyDriver(new CoverageResult([
            $fileA => new FileCoverage($fileA, [$refA->getStartLine() => new LineCoverage($refA->getStartLine(), LineStatus::Executed)]),
            $fileB => new FileCoverage($fileB, [$refB->getStartLine() => new LineCoverage($refB->getStartLine(), LineStatus::Executed)]),
            '/src/Unrelated.php' => new FileCoverage('/src/Unrelated.php', [5 => new LineCoverage(5, LineStatus::Executed)]),
        ]));

        $interceptor = new CoverageTestInterceptor($driver);
        // testCoversBoth has #[Covers(TargetClassA::class)] and #[Covers(TargetClassB::class)]
        $info = self::makeTestInfo(CoveredByAttribute::class, 'testCoversBoth');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert
        $coverage = $result->getAttribute(CoverageResult::class);
        Assert::instanceOf($coverage, CoverageResult::class);
        Assert::count($coverage->files, 2);
        Assert::true(isset($coverage->files[$fileA]));
        Assert::true(isset($coverage->files[$fileB]));
        Assert::false(isset($coverage->files['/src/Unrelated.php']));
    }

    public function coversMethodFiltersToMethodLinesOnly(): void
    {
        // Arrange
        $fileA = (new \ReflectionClass(TargetClassA::class))->getFileName();
        $methodRef = new \ReflectionMethod(TargetClassA::class, 'doSomething');
        $methodStart = $methodRef->getStartLine();
        $methodEnd = $methodRef->getEndLine();

        // Coverage includes lines both inside and outside the method
        $lines = [];
        $lines[1] = new LineCoverage(1, LineStatus::Executed);   // outside method (class declaration)
        $lines[$methodStart] = new LineCoverage($methodStart, LineStatus::Executed);  // inside method
        if ($methodEnd > $methodStart) {
            $lines[$methodEnd] = new LineCoverage($methodEnd, LineStatus::Executed); // inside method
        }
        $lines[999] = new LineCoverage(999, LineStatus::Executed); // outside method

        $driver = new SpyDriver(new CoverageResult([
            $fileA => new FileCoverage($fileA, $lines),
        ]));

        $interceptor = new CoverageTestInterceptor($driver);
        // testCoversMethod has #[Covers(TargetClassA::class, 'doSomething')]
        $info = self::makeTestInfo(CoveredByAttribute::class, 'testCoversMethod');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert — only lines within the method range should remain
        $coverage = $result->getAttribute(CoverageResult::class);
        Assert::instanceOf($coverage, CoverageResult::class);
        Assert::count($coverage->files, 1);

        $fileCoverage = $coverage->files[$fileA];
        foreach ($fileCoverage->lines as $line => $_) {
            Assert::true($line >= $methodStart && $line <= $methodEnd,
                "Line {$line} should be within method range {$methodStart}-{$methodEnd}");
        }

        Assert::false(isset($fileCoverage->lines[1]));
        Assert::false(isset($fileCoverage->lines[999]));
    }

    public function noCoversAttributeKeepsAllCoverage(): void
    {
        // Arrange
        $driver = new SpyDriver(new CoverageResult([
            '/src/Foo.php' => new FileCoverage('/src/Foo.php', [10 => new LineCoverage(10, LineStatus::Executed)]),
            '/src/Bar.php' => new FileCoverage('/src/Bar.php', [20 => new LineCoverage(20, LineStatus::Executed)]),
        ]));

        $interceptor = new CoverageTestInterceptor($driver);
        $info = self::makeTestInfo(CoveredByAttribute::class, 'testNoCovers');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        // Act
        $result = $interceptor->runTest($info, $next);

        // Assert — no filtering, both files kept
        $coverage = $result->getAttribute(CoverageResult::class);
        Assert::instanceOf($coverage, CoverageResult::class);
        Assert::count($coverage->files, 2);
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
