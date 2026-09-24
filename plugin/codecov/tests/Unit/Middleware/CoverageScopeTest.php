<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Middleware;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\CoverageScope;
use Testo\Codecov\Covers;
use Testo\Codecov\CoversNothing;
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
use Testo\Expect;
use Testo\Test;
use Tests\Codecov\Stub\CoveredByAttribute;
use Tests\Codecov\Stub\SpyDriver;
use Tests\Codecov\Stub\TargetClassA;
use Tests\Codecov\Stub\TargetClassB;

#[Test]
#[Covers(CoverageTestInterceptor::class)]
#[Covers(CoverageScope::class)]
final class CoverageScopeTest
{
    public function scopeFiltersATestWithoutAttributes(): void
    {
        $interceptor = new CoverageTestInterceptor(self::driverCoveringBothTargets());
        $info = self::makeTestInfo('testNoCovers')
            ->withAttribute(CoverageScope::class, new CoverageScope(new Covers(TargetClassA::class)));

        $coverage = self::coverageOf($interceptor->runTest($info, self::pass(...)));

        Assert::count($coverage->files, 1);
        Assert::true(isset($coverage->files[self::fileOf(TargetClassA::class)]));
    }

    public function testsOwnCoversWinOverTheScope(): void
    {
        $interceptor = new CoverageTestInterceptor(self::driverCoveringBothTargets());
        # testCoversA declares #[Covers(TargetClassA::class)]; the scope would keep B.
        $info = self::makeTestInfo('testCoversA')
            ->withAttribute(CoverageScope::class, new CoverageScope(new Covers(TargetClassB::class)));

        $coverage = self::coverageOf($interceptor->runTest($info, self::pass(...)));

        Assert::count($coverage->files, 1);
        Assert::true(isset($coverage->files[self::fileOf(TargetClassA::class)]));
    }

    public function scopeOptsInATestOfAnUnselectedType(): void
    {
        $interceptor = new CoverageTestInterceptor(self::driverCoveringBothTargets(), ['test']);
        $info = self::makeTestInfo('testNoCovers', type: 'fixture')
            ->withAttribute(CoverageScope::class, new CoverageScope(new Covers(TargetClassB::class)));

        $coverage = self::coverageOf($interceptor->runTest($info, self::pass(...)));

        Assert::count($coverage->files, 1);
        Assert::true(isset($coverage->files[self::fileOf(TargetClassB::class)]));
    }

    public function unselectedTypeWithoutScopeIsNotMeasured(): void
    {
        $interceptor = new CoverageTestInterceptor(self::driverCoveringBothTargets(), ['test']);

        $result = $interceptor->runTest(self::makeTestInfo('testNoCovers', type: 'fixture'), self::pass(...));

        Assert::null($result->getAttribute(CoverageResult::class));
    }

    public function coversNothingScopeSkipsCollection(): void
    {
        $interceptor = new CoverageTestInterceptor(self::driverCoveringBothTargets());
        $info = self::makeTestInfo('testNoCovers')
            ->withAttribute(CoverageScope::class, new CoverageScope(new CoversNothing()));

        $result = $interceptor->runTest($info, self::pass(...));

        Assert::null($result->getAttribute(CoverageResult::class));
    }

    public function emptyScopeIsRejected(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        new CoverageScope();
    }

    public function mixedScopeIsRejected(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        new CoverageScope(new Covers(TargetClassA::class), new CoversNothing());
    }

    private static function driverCoveringBothTargets(): SpyDriver
    {
        $files = [];
        foreach ([TargetClassA::class, TargetClassB::class] as $class) {
            $reflection = new \ReflectionClass($class);
            $file = (string) $reflection->getFileName();
            $line = (int) $reflection->getStartLine();
            $files[$file] = new FileCoverage($file, [$line => new LineCoverage($line, LineStatus::Executed)]);
        }

        return new SpyDriver(new CoverageResult($files));
    }

    private static function coverageOf(TestResult $result): CoverageResult
    {
        $coverage = $result->getAttribute(CoverageResult::class);
        Assert::instanceOf($coverage, CoverageResult::class);

        return $coverage;
    }

    /**
     * @param class-string $class
     */
    private static function fileOf(string $class): string
    {
        return (string) (new \ReflectionClass($class))->getFileName();
    }

    private static function pass(TestInfo $info): TestResult
    {
        return new TestResult($info, Status::Passed);
    }

    /**
     * @param non-empty-string $method
     * @param non-empty-string $type
     */
    private static function makeTestInfo(string $method, string $type = 'test'): TestInfo
    {
        return new TestInfo(
            name: $method,
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Codecov/Unit'),
                definition: new CaseDefinition(
                    name: CoveredByAttribute::class,
                    type: $type,
                    file: Path::create(__FILE__),
                    reflection: new \ReflectionClass(CoveredByAttribute::class),
                ),
            ),
            testDefinition: new TestDefinition(new \ReflectionMethod(CoveredByAttribute::class, $method)),
        );
    }
}
