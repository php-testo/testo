<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

use Testo\Codecov\CoversNothing;

/**
 * Stub test case with CoversNothing on the class.
 */
#[CoversNothing]
final class UncoveredClass
{
    public function testA(): void {}

    public function testB(): void {}
}
