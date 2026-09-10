<?php

declare(strict_types=1);

namespace Tests\Core\Testing\Stub\Plugin;

use Internal\Destroy\Destroyable;

/**
 * Flips {@see self::$destroyed} when the container tears it down — lets a test observe that
 * {@see \Testo\Testing\Internal\Mock\MockContainer::destroy()} reaches the services it manages.
 */
final class DestroyableStubService implements Destroyable
{
    public bool $destroyed = false;

    #[\Override]
    public function destroy(): void
    {
        $this->destroyed = true;
    }
}
