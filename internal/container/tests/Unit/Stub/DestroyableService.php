<?php

declare(strict_types=1);

namespace Internal\Container\Tests\Unit\Stub;

use Internal\Destroy\Destroyable;

/**
 * Records whether the container tore it down, so tests can observe {@see ObjectContainer::destroy()}.
 *
 * @internal
 */
final class DestroyableService implements Destroyable
{
    public bool $destroyed = false;

    #[\Override]
    public function destroy(): void
    {
        $this->destroyed = true;
    }
}
