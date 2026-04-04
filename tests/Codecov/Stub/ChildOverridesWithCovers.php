<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

use Testo\Codecov\Covers;

/**
 * Child overrides parent's CoversNothing with explicit Covers.
 */
#[Covers(TargetClassA::class)]
final class ChildOverridesWithCovers extends CoversNothingParent
{
    public function testWithCovers(): void {}
}
