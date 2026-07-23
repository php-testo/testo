<?php

declare(strict_types=1);

namespace Tests\Bridge\Revolt\Unit;

use Testo\Assert;
use Testo\Bridge\Revolt\Internal\RevoltTestBatchRunner;
use Testo\Bridge\Revolt\Internal\RunInRevoltInterceptor;
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Bridge\Revolt\Strategy;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Value\Status;
use Testo\Test;

/**
 * Unit: {@see RunInRevoltInterceptor::runTestCase()} wires the case for the chosen {@see Strategy}
 * without touching the event loop — {@see Strategy::PerCase} hands the case a batch runner,
 * {@see Strategy::PerTest} leaves it alone (the loop is entered per test in `runTest()` instead).
 */
#[Test]
#[Covers(RunInRevoltInterceptor::class)]
#[Covers(Strategy::class)]
#[Covers(RevoltTestBatchRunner::class)]
final class RunInRevoltInterceptorTest
{
    public function perCaseStrategySetsBatchRunnerOnTheCase(): void
    {
        $captured = null;
        $next = static function (CaseInfo $info) use (&$captured): CaseResult {
            $captured = $info;
            return new CaseResult(results: [], status: Status::Passed);
        };

        (new RunInRevoltInterceptor(new RunInRevolt(Strategy::PerCase)))
            ->runTestCase($this->caseInfo(), $next);

        Assert::notNull($captured?->batchRunner);
    }

    public function perTestStrategyLeavesTheCaseRunnerUnset(): void
    {
        $captured = null;
        $next = static function (CaseInfo $info) use (&$captured): CaseResult {
            $captured = $info;
            return new CaseResult(results: [], status: Status::Passed);
        };

        (new RunInRevoltInterceptor(new RunInRevolt(Strategy::PerTest)))
            ->runTestCase($this->caseInfo(), $next);

        Assert::same(null, $captured?->batchRunner);
    }

    public function defaultStrategyIsPerTest(): void
    {
        Assert::same(Strategy::PerTest, (new RunInRevolt())->strategy);
    }

    private function caseInfo(): CaseInfo
    {
        return new CaseInfo(definition: new CaseDefinition(name: 'RevoltCase', type: 'test'));
    }
}
