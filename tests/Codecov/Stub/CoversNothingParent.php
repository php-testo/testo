<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

use Testo\Codecov\CoversNothing;

/**
 * Parent with CoversNothing on the class.
 */
#[CoversNothing]
abstract class CoversNothingParent
{
    public function testInherited(): void {}
}
