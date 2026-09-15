<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Stub\FullySkipped;

use Testo\Lifecycle\AfterClass;
use Testo\Lifecycle\BeforeClass;
use Testo\Test;
use Testo\Skip;

/**
 * Class-based analog of the fully skipped function case in `fully_skipped_functions.php`
 * ({@see skippedFnOne()}): the hooks are the case's non-tests, so they never depended on the
 * surviving tests — pinned here so both flavors stay in lockstep.
 *
 * Static hook counters accumulate across directory runs — feature tests assert deltas. The hooks
 * are static so the fully skipped class is never instantiated.
 */
#[Test]
final class FullySkippedClassStub
{
    public static int $beforeClassCalls = 0;
    public static int $afterClassCalls = 0;

    #[BeforeClass]
    public static function bootCase(): void
    {
        ++self::$beforeClassCalls;
    }

    #[AfterClass]
    public static function shutdownCase(): void
    {
        ++self::$afterClassCalls;
    }

    #[Skip('the whole class case is skipped')]
    public function skipped(): void
    {
        throw new \LogicException('Must never run: the test is skipped.');
    }
}
