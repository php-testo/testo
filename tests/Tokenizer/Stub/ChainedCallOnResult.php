<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Stub;

// Method chained on the return value of a $this call: the second call starts with
// T_OBJECT_OPERATOR without a resolvable class — the invocation is skipped (non-detectable).
final class ChainedCallOnResult
{
    public function run(): void
    {
        $this->getHelper()->doSomething();
    }

    public function getHelper(): object
    {
        return new \stdClass();
    }
}
