<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Internal;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Internal\CoverageCollector;
use Testo\Codecov\Report\CoverageReport;
use Testo\Codecov\Result\CoverageResult;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;
use Testo\Data\MultipleResult;
use Testo\Test;

#[Test]
final class CoverageCollectorTest
{
    /**
     * For data-driven tests the top-level result is a {@see MultipleResult} wrapper that
     * carries no coverage of its own — coverage lives on the per-data-set child results.
     * The collector must descend into them, otherwise reports come out empty.
     */
    public function collectsCoverageFromDataSetChildResults(): void
    {
        $report = self::createReport();
        $collector = new CoverageCollector([$report]);

        $childA = self::createTestResult()
            ->withAttribute(CoverageResult::class, CoverageResult::fromRawData(['/src/A.php' => [10 => 1]]));
        $childB = self::createTestResult()
            ->withAttribute(CoverageResult::class, CoverageResult::fromRawData(['/src/B.php' => [20 => 1]]));

        $summary = new MultipleResult([$childA, $childB]);
        $aggregate = self::createTestResult()->withAttribute(MultipleResult::class, $summary);

        $collector->mergeSuiteResult(self::suiteOf($aggregate));
        $collector->destroy();

        Assert::true($report->result instanceof CoverageResult);
        Assert::array($report->result->files)->hasKeys('/src/A.php', '/src/B.php');
    }

    /**
     * Plain (non data-driven) tests carry coverage directly on the test result.
     */
    public function collectsCoverageFromPlainTestResult(): void
    {
        $report = self::createReport();
        $collector = new CoverageCollector([$report]);

        $result = self::createTestResult()
            ->withAttribute(CoverageResult::class, CoverageResult::fromRawData(['/src/Plain.php' => [5 => 1]]));

        $collector->mergeSuiteResult(self::suiteOf($result));
        $collector->destroy();

        Assert::true($report->result instanceof CoverageResult);
        Assert::array($report->result->files)->hasKeys('/src/Plain.php');
    }

    private static function suiteOf(TestResult $result): SuiteResult
    {
        return new SuiteResult(
            results: [new CaseResult(results: [$result], status: Status::Passed)],
            status: Status::Passed,
        );
    }

    private static function createReport(): CoverageReport
    {
        return new class() implements CoverageReport {
            public ?CoverageResult $result = null;

            #[\Override]
            public function generate(CoverageResult $result): void
            {
                $this->result = $result;
            }
        };
    }

    private static function createTestResult(): TestResult
    {
        $reflection = new \ReflectionMethod(self::class, 'createTestResult');
        $caseInfo = new CaseInfo(suiteIdentity: new SuiteIdentity('Codecov/Unit'), definition: new CaseDefinition(name: 'TestCase', type: 'test', file: Path::create(__FILE__)));
        $testDefinition = new TestDefinition(reflection: $reflection);
        $info = new TestInfo(name: 'testMethod', caseInfo: $caseInfo, testDefinition: $testDefinition);

        return new TestResult(info: $info, status: Status::Passed);
    }
}
