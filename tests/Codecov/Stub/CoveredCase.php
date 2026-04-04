<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

/**
 * Stub test case without CoversNothing — coverage should be collected.
 */
final class CoveredCase
{
    public function testSomething(): void {}
}
