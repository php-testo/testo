<?php

declare(strict_types=1);

namespace Tests\Test\Unit\Fixture;

use Testo\Test\Skip;

/**
 * Used by {@see \Tests\Test\Unit\Internal\SkipInterceptorTest}: a case mixing parked and
 * enabled tests. Lives in Fixture (excluded from discovery), so the throwing bodies never run.
 */
final class SkipMixedMethodsFixture
{
    /**
     * Checks that order totals include the reworked pricing.
     */
    #[Skip('broken by the pricing rework, see ISSUE-123')]
    public function parked(): void
    {
        throw new \LogicException('Must never run: the test is parked.');
    }

    #[Skip]
    public function parkedNoReason(): void
    {
        throw new \LogicException('Must never run: the test is parked.');
    }

    public function enabled(): void {}
}
