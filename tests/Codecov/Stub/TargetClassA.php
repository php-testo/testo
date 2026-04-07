<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

/**
 * Dummy source class used as a coverage target.
 */
final class TargetClassA
{
    public function doSomething(): void {}

    private function internalHelper(): string
    {
        return 'helper';
    }
}
