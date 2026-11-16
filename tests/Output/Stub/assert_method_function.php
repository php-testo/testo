<?php

declare(strict_types=1);

namespace Tests\Output\Stub;

use Testo\Common\Attribute\AssertMethod;

#[AssertMethod]
function runAssertFunction(callable $callback): mixed
{
    return $callback();
}
