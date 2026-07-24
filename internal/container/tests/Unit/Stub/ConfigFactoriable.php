<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit\Stub;

use Internal\Container\Factoriable;

/**
 * {@see Factoriable} stub — a private constructor plus an autowired static `create()`, so it can only be
 * built through the factory path a binding selects for Factoriable classes.
 *
 * @internal
 */
final class ConfigFactoriable implements Factoriable
{
    private function __construct(
        public ContainerScopeService $service,
    ) {}

    public static function create(ContainerScopeService $service): self
    {
        return new self($service);
    }
}
