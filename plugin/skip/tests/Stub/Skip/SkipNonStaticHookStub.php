<?php

declare(strict_types=1);

namespace Tests\Skip\Stub\Skip;

use Testo\Lifecycle\BeforeClass;
use Testo\Test;
use Testo\Skip;

/**
 * A fully skipped case with a non-static class-level hook: the hook must not run, so nothing forces
 * the class to be built.
 *
 * Both counters accumulate across directory runs —
 * {@see \Tests\Skip\Feature\SkipFeatureTest::fullySkippedCaseRunsNoClassHooksAndIsNotBuiltForThem()}
 * asserts the deltas.
 */
#[Test]
#[Skip('fully skipped, the non-static hook must stay silent')]
final class SkipNonStaticHookStub
{
    public static int $constructions = 0;
    public static int $hookCalls = 0;

    public function __construct()
    {
        ++self::$constructions;
    }

    #[BeforeClass]
    public function bootCase(): void
    {
        ++self::$hookCalls;
    }

    public function skipped(): void
    {
        throw new \LogicException('Must never run: the case is skipped.');
    }
}
