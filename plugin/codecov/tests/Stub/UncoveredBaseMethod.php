<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

use Testo\Codecov\CoversNothing;

/**
 * Base class with CoversNothing on a method.
 */
abstract class UncoveredBaseMethod
{
    #[CoversNothing]
    public function testMarkedInBase(): void {}
}
