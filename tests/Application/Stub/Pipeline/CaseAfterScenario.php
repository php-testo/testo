<?php

declare(strict_types=1);

namespace Tests\Application\Stub\Pipeline;

use Testo\Assert;
use Testo\Test;

/**
 * Case-level scenario: the interceptor throws in `runTestCase` AFTER `$next()`,
 * so the tests below actually run (and pass) but the resulting {@see CaseResult}
 * is discarded when the throw unwinds the case pipeline.
 */
#[Test]
#[FailPipeline(FailStage::CaseAfter)]
final class CaseAfterScenario
{
    public function runsButResultIsLostA(): void
    {
        Assert::same(1, 1);
    }

    public function runsButResultIsLostB(): void
    {
        Assert::same(1, 1);
    }
}
