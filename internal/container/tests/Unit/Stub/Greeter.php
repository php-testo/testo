<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit\Stub;

/**
 * @internal
 */
final class Greeter implements GreeterInterface
{
    public function greet(): string
    {
        return 'hi';
    }
}
