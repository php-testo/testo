<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Assert;
use Testo\Test;
use Testo\Skip;

/**
 * Method-level `#[Skip]`, with and without a reason: only the marked tests of the case are
 * skipped; the unmarked neighbor still runs. Both marked bodies throw, so a marked test that
 * gets past the skip interceptor anyway fails loudly instead of passing quietly.
 */
#[Test]
final class SkipMethodStub
{
    #[Skip('broken by the pricing rework, see ISSUE-123')]
    public function skipped(): void
    {
        throw new \LogicException('Must never run: the test is skipped.');
    }

    #[Skip]
    public function skippedNoReason(): void
    {
        throw new \LogicException('Must never run: the test is skipped.');
    }

    public function enabled(): void
    {
        # Control neighbor: stays runnable next to the skipped ones.
        Assert::true(true);
    }
}
