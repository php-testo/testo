<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit\Stub;

/**
 * Autowirable service with a single object dependency, used to observe constructor injection.
 *
 * @internal
 */
final class DependentService
{
    public function __construct(
        public ContainerScopeService $dependency,
    ) {}
}
