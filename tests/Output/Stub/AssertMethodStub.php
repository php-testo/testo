<?php

declare(strict_types=1);

namespace Tests\Output\Stub;

use Testo\Attribute\AssertMethod;

final class AssertMethodStub
{
    #[AssertMethod]
    public static function run(callable $callback): mixed
    {
        return $callback();
    }
}
