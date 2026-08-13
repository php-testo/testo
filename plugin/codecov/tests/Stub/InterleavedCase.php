<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

/**
 * Stub case with two tests, for driving an interleave of two distinct test identities.
 */
final class InterleavedCase
{
    public function testFirst(): void {}

    public function testSecond(): void {}
}
