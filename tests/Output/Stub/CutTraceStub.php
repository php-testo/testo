<?php

declare(strict_types=1);

namespace Tests\Output\Stub;

use Testo\Output\Rendering\CutTrace;

final class CutTraceStub
{
    #[CutTrace]
    public static function run(callable $callback): mixed
    {
        return $callback();
    }
}
