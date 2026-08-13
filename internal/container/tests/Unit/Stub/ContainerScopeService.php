<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit\Stub;

/**
 * Trivial autowirable service used to observe which container/scope resolves it.
 *
 * @internal
 */
final class ContainerScopeService
{
    /** @var int Distinguishes instances produced by different scopes/containers. */
    public int $tag = 0;
}
