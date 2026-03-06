<?php

declare(strict_types=1);

namespace Tests\Output\Stub;

final class ThrowingStub
{
    public static function fail(): never
    {
        throw new \RuntimeException('test');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function captureTrace(): array
    {
        return \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
    }
}
