<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

use Testo\Codecov\Covers;

/**
 * Test case with #[Covers] attributes on methods.
 */
final class CoveredByAttribute
{
    #[Covers(TargetClassA::class)]
    public function testCoversA(): void {}

    #[Covers(TargetClassA::class)]
    #[Covers(TargetClassB::class)]
    public function testCoversBoth(): void {}

    #[Covers(TargetClassA::class, 'doSomething')]
    public function testCoversMethod(): void {}

    public function testNoCovers(): void {}
}
