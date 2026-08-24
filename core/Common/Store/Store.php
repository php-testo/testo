<?php

declare(strict_types=1);

namespace Testo\Common\Store;

/**
 * A single opened store: a named, versioned document that survives between runs.
 *
 * A store is **never a source of truth**. Its whole contents may vanish — deleted, corrupted, or
 * invalidated by a fingerprint change — and the next run must still work, only slower. Every method
 * is fail-open: I/O failures are reported as diagnostics and swallowed, they never change the run's
 * outcome. Only programming errors (opening a suite store outside a suite scope) throw.
 *
 * This interface is meant to be **consumed, not implemented** by userland code. The implementation is
 * provided by the framework via {@see Stores::open()}.
 *
 * @api
 */
interface Store
{
    /**
     * Read the payload, or `null` when there is nothing usable to read — the store is absent,
     * corrupted, has a different schema, or its fingerprint drifted. These are one state to the
     * caller; the reason survives only as a diagnostic.
     *
     * @return array<array-key, mixed>|null
     */
    public function load(): ?array;

    /**
     * Overwrite the payload atomically, stamping it with the current schema and fingerprint. A
     * partially written file is never observable. On an I/O failure the write is dropped, not thrown.
     *
     * @param array<array-key, mixed> $payload
     */
    public function save(array $payload): void;

    /**
     * Read-modify-write under an exclusive lock: `$fn` receives the current payload (or `null`) and
     * returns the next one, which is then written atomically — or `null` to leave the store untouched.
     * Use for incremental updates — merging a run's outcomes, bumping counters. `$fn` is not invoked
     * when the lock cannot be acquired. Not reentrant: a nested `update()` of the same store stalls
     * until the lock attempt times out, and the inner write is skipped.
     *
     * @param \Closure(array<array-key, mixed>|null): (array<array-key, mixed>|null) $fn
     */
    public function update(\Closure $fn): void;

    /**
     * Remove the store from disk. Silent and idempotent.
     */
    public function delete(): void;
}
