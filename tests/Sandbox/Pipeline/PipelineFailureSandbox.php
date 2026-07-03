<?php

declare(strict_types=1);

namespace Tests\Sandbox\Pipeline;

use Testo\Assert;
use Testo\Test;
use Tests\Application\Stub\Pipeline\FailPipeline;
use Tests\Application\Stub\Pipeline\FailStage;

/**
 * Sandbox playground for pipeline (interceptor) failures. Just enable the `sandbox` suite in
 * `testo.php` — the {@see FailPipeline} attribute self-binds the failing interceptor via
 * {@see \Testo\Pipeline\Attribute\FallbackInterceptor}, so no plugin registration is needed.
 *
 * Run it live:
 *   php vendor/bin/testo run --suite=sandbox --path="tests/Sandbox/Pipeline/PipelineFailureSandbox.php"
 */

/**
 * Test-level throws: the interceptor explodes around a single test's `$next()`, the rest of the
 * case keeps running. Both marked tests end up Aborted; `passesCleanly` stays green.
 */
#[Test]
final class TestLevelPipelineFailure
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

/**
 * Case-level throw BEFORE `$next()`: the whole case is aborted before any test runs.
 */
#[Test]
#[FailPipeline(FailStage::CaseBefore)]
final class CaseBeforePipelineFailure
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

/**
 * Case-level throw AFTER `$next()`: the tests run and pass, but the case result is discarded when
 * the throw unwinds the pipeline — watch them disappear from the summary.
 */
#[Test]
#[FailPipeline(FailStage::CaseAfter)]
final class CaseAfterPipelineFailure
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
