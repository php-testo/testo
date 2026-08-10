<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit\Stub;

use Internal\Container\Container;
use Internal\Container\Inflector;

/**
 * Bumps the tag of every {@see ContainerScopeService} it inflects, so tests can observe that the
 * container ran its inflectors over a resolved service.
 *
 * @internal
 */
final class TagInflector implements Inflector
{
    #[\Override]
    public function inflect(object $object, Container $container): object
    {
        $object instanceof ContainerScopeService and $object->tag += 100;
        return $object;
    }
}
