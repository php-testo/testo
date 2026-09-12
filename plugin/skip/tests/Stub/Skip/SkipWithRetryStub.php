<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Retry;
use Testo\Test;
use Testo\Skip;

/**
 * `#[Skip]` composed with `#[Retry]`: the retry policy is resolved in the per-test pipeline, which
 * a skipped test never enters, so the body must not run at all. The counter tells a single stray
 * run apart from a full retry cycle. The enabled neighbor carries the same attribute with the flaky
 * mark off and fails its first attempt on purpose: two attempts per run prove the policy is live
 * in this suite.
 */
#[Test]
final class SkipWithRetryStub
{
    public static int $attempts = 0;
    public static int $enabledAttempts = 0;

    /**
     * Per-run marker for the control neighbor. An instance property, not a static: the case
     * instance is built anew for every run of the case and shared by all retry attempts within
     * it, so the marker starts fresh each run and never depends on how many attempts earlier
     * runs (a `--filter` on one method, an aborted run) left behind.
     */
    private bool $firstAttemptFailed = false;

    #[Skip('skipped, retry must not engage')]
    #[Retry(maxAttempts: 3)]
    public function skipped(): void
    {
        ++self::$attempts;
        throw new \LogicException('Must never run: the test is skipped.');
    }

    #[Retry(maxAttempts: 3, markFlaky: false)]
    public function enabled(): void
    {
        ++self::$enabledAttempts;

        # Control neighbor: the first attempt of every run fails, the second passes — two attempts per run.
        if (!$this->firstAttemptFailed) {
            $this->firstAttemptFailed = true;
            throw new \RuntimeException('First attempt fails by design.');
        }
    }
}
