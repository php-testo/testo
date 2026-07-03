<?php

declare(strict_types=1);

namespace Tests\Application\Stub\Pipeline;

use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Marks a test method (or a whole test case) so that {@see FailingInterceptor}
 * throws at the configured {@see FailStage}.
 *
 * The attribute is self-binding: {@see FallbackInterceptor} points the pipeline's
 * {@see \Testo\Pipeline\Internal\AttributesInterceptor} at {@see FailingInterceptor}, so the
 * interceptor is attached wherever this attribute appears — no plugin registration required.
 *
 * Placed on a method → drives the test-level stages ({@see FailStage::TestBefore},
 * {@see FailStage::TestAfter}); placed on a class → drives the case-level stages
 * ({@see FailStage::CaseBefore}, {@see FailStage::CaseAfter}).
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
#[FallbackInterceptor(FailingInterceptor::class)]
final readonly class FailPipeline implements Interceptable
{
    public function __construct(
        public FailStage $stage,
    ) {}
}
