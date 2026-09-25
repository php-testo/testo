<?php

declare(strict_types=1);

namespace Testo\Common\Store;

use Testo\Common\Store;

/**
 * Registry of stores — the single entry point for opening persistent, cross-run storage.
 *
 * Inject this and call {@see open()} with a {@see StoreDefinition}. A suite-scoped definition resolves
 * against the currently active suite, so it may only be opened while a suite is running.
 *
 * This interface is meant to be **consumed, not implemented** by userland code; the framework binds
 * the implementation.
 *
 * @api
 */
interface Stores
{
    /**
     * Open the store described by the definition. Cheap — resolves paths, does no I/O until the
     * returned {@see Store} is read or written.
     *
     * A {@see StoreScope::Suite} store is bound to the suite that is active at this call: it stays
     * valid only within that suite. Do not cache it across suites — reopen instead, or the handle
     * keeps addressing the previous suite's data.
     *
     * @throws \LogicException When a {@see StoreScope::Suite} store is opened outside a suite scope.
     */
    public function open(StoreDefinition $definition): Store;
}
