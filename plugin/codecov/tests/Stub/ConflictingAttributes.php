<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

use Testo\Codecov\Covers;
use Testo\Codecov\CoversNothing;

/**
 * Stub with conflicting coverage attributes on the same method.
 */
final class ConflictingAttributes
{
    #[Covers(TargetClassA::class)]
    #[CoversNothing]
    public function testConflictOnMethod(): void {}
}
