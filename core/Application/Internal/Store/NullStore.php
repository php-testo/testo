<?php

declare(strict_types=1);

namespace Testo\Application\Internal\Store;

use Testo\Common\Store;

/**
 * The store handed out when the subsystem is disabled: no data, no writes.
 *
 * Indistinguishable to the owner from "nothing has been recorded yet" — {@see load()} returns `null`,
 * mutations are dropped. `$fn` in {@see update()} is deliberately not invoked: there is no data to
 * modify and nothing to persist.
 *
 * @internal
 */
final readonly class NullStore implements Store
{
    #[\Override]
    public function load(): ?array
    {
        return null;
    }

    #[\Override]
    public function save(array $payload): void {}

    #[\Override]
    public function update(\Closure $fn): void {}

    #[\Override]
    public function delete(): void {}
}
