<?php

declare(strict_types=1);

namespace Tests\Application\Stub\Pipeline;

use Testo\Assert;
use Testo\Test;

/**
 * Case-level scenario: the interceptor throws in `runTestCase` BEFORE `$next()`,
 * so none of the tests below ever execute.
 */
#[Test]
#[FailPipeline(FailStage::CaseBefore)]
final class CaseBeforeScenario
{
    public function neverRunsA(): void
    {
        Assert::same(1, 1);
    }

    public function neverRunsB(): void
    {
        Assert::same(1, 1);
    }
}
