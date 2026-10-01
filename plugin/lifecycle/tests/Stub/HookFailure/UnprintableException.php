<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Stub\HookFailure;

final class UnprintableException extends \RuntimeException
{
    public function __toString(): string
    {
        throw new \LogicException('an exception that cannot be printed');
    }
}
