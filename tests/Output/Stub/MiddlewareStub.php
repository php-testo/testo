<?php

declare(strict_types=1);

namespace Tests\Output\Stub;

final class MiddlewareStub
{
    public static function run(callable $callback): mixed
    {
        return $callback();
    }

    public static function runDeep(callable $callback): mixed
    {
        return self::deep1($callback);
    }

    private static function deep1(callable $callback): mixed
    {
        return self::deep2($callback);
    }

    private static function deep2(callable $callback): mixed
    {
        return $callback();
    }
}
