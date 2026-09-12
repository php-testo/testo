<?php

declare(strict_types=1);

namespace Tests\Skip\Unit\Fixture;

use Testo\Skip;

/**
 * Fixture mixing skipped and enabled tests.
 *
 * Used by {@see \Tests\Skip\Unit\Internal\SkipInterceptorTest}: one test is skipped with a reason,
 * one without a reason, and one stays enabled to show what the interceptor leaves alone. The
 * PHPDoc summary of the skipped test is the description {@see \Testo\Skip\Internal\SkipInterceptor}
 * copies into the synthetic result.
 */
final class SkipMixedMethodsFixture
{
    /**
     * Checks that order totals include the reworked pricing.
     */
    #[Skip('broken by the pricing rework, see ISSUE-123')]
    public function skipped(): void {}

    #[Skip]
    public function skippedNoReason(): void {}

    public function enabled(): void {}
}
