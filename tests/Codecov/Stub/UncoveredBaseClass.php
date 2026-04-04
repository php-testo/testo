<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

use Testo\Codecov\CoversNothing;

/**
 * Abstract base with CoversNothing.
 */
#[CoversNothing]
abstract class UncoveredBaseClass
{
    public function testInherited(): void {}
}
