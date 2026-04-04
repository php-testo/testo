<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

use Testo\Codecov\CoversNothing;

/**
 * Stub test case with CoversNothing on a method.
 */
final class UncoveredMethod
{
    #[CoversNothing]
    public function testIgnored(): void {}

    public function testCovered(): void {}
}
