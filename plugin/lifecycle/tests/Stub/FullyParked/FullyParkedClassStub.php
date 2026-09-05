<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Stub\FullyParked;

use Testo\Lifecycle\AfterClass;
use Testo\Lifecycle\BeforeClass;
use Testo\Test;
use Testo\Test\Skip;

/**
 * Class-based analog of the fully parked function case: for a class the hooks are resolved from
 * {@see \Testo\Core\Definition\CaseDefinition::$reflection}, so they never depended on the
 * surviving tests — pinned here so both flavors stay in lockstep.
 *
 * Static hook counters accumulate across catalog runs — feature tests assert deltas. The hooks
 * are static so the fully parked class is never instantiated.
 */
#[Test]
final class FullyParkedClassStub
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

    #[Skip('the whole class case is parked')]
    public function parked(): void
    {
        throw new \LogicException('Must never run: the test is parked.');
    }
}
