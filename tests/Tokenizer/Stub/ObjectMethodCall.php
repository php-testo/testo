<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Stub;

// A method call on a non-$this variable — the T_OBJECT_OPERATOR is seen with no
// tracked invocation (invocationTID is false/0), which exercises the
// empty($invocationTID) === true branch in locateInvocations.
final class ObjectMethodCall
{
    public function run(): void
    {
        $obj = new \stdClass();
        $obj->doWork();
    }
}
