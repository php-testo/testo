<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit\Stub;

/**
 * Requires a scalar the autowirer cannot supply on its own, so `make()`/`get()` fail unless the value
 * is provided via arguments or a binding.
 *
 * @internal
 */
final class UnresolvableService
{
    public function __construct(
        public int $count,
    ) {}
}
