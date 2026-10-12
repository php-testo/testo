<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Middleware;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Internal\Middleware\CoverageTestInterceptor;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\FileCoverage;
use Testo\Codecov\Result\LineCoverage;
use Testo\Codecov\Result\LineStatus;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
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
    public function coversClassCreditsTargetFileOnly(): void
    {
        // Driver returns coverage for both target files.
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
        // testCoversA has #[Covers(TargetClassA::class)] — only fileA is credited
        $info = self::makeTestInfo(CoveredByAttribute::class, 'testCoversA');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        $result = $interceptor->runTest($info, $next);

        $coverage = $result->getAttribute(CoverageResult::class);
        Assert::instanceOf($coverage, CoverageResult::class);
        Assert::same(self::executedFiles($coverage), [$fileA]);
        Assert::same($coverage->files[$fileB]->lines[$refB->getStartLine()]->status, LineStatus::NotExecuted);
    }

    public function multipleCoversCreditsAllTargetFiles(): void
    {
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

        $result = $interceptor->runTest($info, $next);

        $coverage = $result->getAttribute(CoverageResult::class);
        Assert::instanceOf($coverage, CoverageResult::class);
        Assert::same(self::executedFiles($coverage), [$fileA, $fileB]);
        Assert::same($coverage->files['/src/Unrelated.php']->lines[5]->status, LineStatus::NotExecuted);
    }

    public function coversMethodCreditsMethodLinesOnly(): void
    {
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

        $result = $interceptor->runTest($info, $next);

        $coverage = $result->getAttribute(CoverageResult::class);
        Assert::instanceOf($coverage, CoverageResult::class);

        foreach ($coverage->files[$fileA]->lines as $line => $lineCoverage) {
            Assert::same(
                $lineCoverage->status,
                $line >= $methodStart && $line <= $methodEnd ? LineStatus::Executed : LineStatus::NotExecuted,
                "Line {$line} is credited only inside method range {$methodStart}-{$methodEnd}",
            );
        }
    }

    public function coversMethodKeepsRestOfFileAsNotExecuted(): void
    {
        $fileA = (new \ReflectionClass(TargetClassA::class))->getFileName();
        $covered = (new \ReflectionMethod(TargetClassA::class, 'doSomething'))->getStartLine();
        $sibling = (new \ReflectionMethod(TargetClassA::class, 'internalHelper'))->getStartLine() + 2;

        $driver = new SpyDriver(new CoverageResult([
            $fileA => new FileCoverage($fileA, [
                $covered => new LineCoverage($covered, LineStatus::Executed),
                $sibling => new LineCoverage($sibling, LineStatus::Executed),
            ]),
        ]));
        $info = self::makeTestInfo(CoveredByAttribute::class, 'testCoversMethod');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        $result = (new CoverageTestInterceptor($driver))->runTest($info, $next);

        $lines = $result->getAttribute(CoverageResult::class)->files[$fileA]->lines;
        Assert::same($lines[$covered]->status, LineStatus::Executed);
        Assert::true(isset($lines[$sibling]), 'A line outside the #[Covers] target must stay in the report.');
        Assert::same($lines[$sibling]->status, LineStatus::NotExecuted);
        Assert::same($lines[$sibling]->testMethods, []);
    }

    public function noCoversAttributeKeepsAllCoverage(): void
    {
        $driver = new SpyDriver(new CoverageResult([
            '/src/Foo.php' => new FileCoverage('/src/Foo.php', [10 => new LineCoverage(10, LineStatus::Executed)]),
            '/src/Bar.php' => new FileCoverage('/src/Bar.php', [20 => new LineCoverage(20, LineStatus::Executed)]),
        ]));

        $interceptor = new CoverageTestInterceptor($driver);
        $info = self::makeTestInfo(CoveredByAttribute::class, 'testNoCovers');
        $next = static fn(TestInfo $i): TestResult => new TestResult($i, Status::Passed);

        $result = $interceptor->runTest($info, $next);

        // No filtering, both files kept.
        $coverage = $result->getAttribute(CoverageResult::class);
        Assert::instanceOf($coverage, CoverageResult::class);
        Assert::count($coverage->files, 2);
    }

    /**
     * @return list<string> Files with at least one line credited to the test.
     */
    private static function executedFiles(CoverageResult $coverage): array
    {
        return \array_keys(\array_filter(
            $coverage->files,
            static fn(FileCoverage $file): bool => \in_array(
                LineStatus::Executed,
                \array_map(static fn(LineCoverage $line): LineStatus => $line->status, $file->lines),
                true,
            ),
        ));
    }

    /**
     * @param class-string $class
     * @param non-empty-string $method
     */
    private static function makeTestInfo(string $class, string $method): TestInfo
    {
        $reflection = new \ReflectionMethod($class, $method);

        return new TestInfo(
            name: $method,
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Codecov/Unit'),
                definition: new CaseDefinition(
                    name: $class,
                    type: 'test',
                    file: Path::create(__FILE__),
                    reflection: new \ReflectionClass($class),
                ),
            ),
            testDefinition: new TestDefinition($reflection),
        );
    }
}
