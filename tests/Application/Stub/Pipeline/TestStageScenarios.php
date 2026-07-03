<?php

declare(strict_types=1);

namespace Tests\Application\Stub\Pipeline;

use Testo\Assert;
use Testo\Test;

/**
 * Test-level scenarios: the interceptor throws around the `$next()` of a single test,
 * while the surrounding case keeps running its other tests.
 *
 * Every method here would pass on its own — the only source of trouble is the
 * {@see FailingInterceptor} reacting to the {@see FailPipeline} attribute.
 */
#[Test]
final class TestStageScenarios
{
    #[FailPipeline(FailStage::TestBefore)]
    public function throwsBeforeNext(): void
    {
        Assert::same(1, 1);
    }

    #[FailPipeline(FailStage::TestAfter)]
    public function throwsAfterNext(): void
    {
        Assert::same(1, 1);
    }

    public function passesCleanly(): void
    {
        Assert::same(1, 1);
    }
}
