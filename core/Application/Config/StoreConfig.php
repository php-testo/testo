<?php

declare(strict_types=1);

namespace Testo\Application\Config;

/**
 * Configuration of the persistent store subsystem.
 *
 * @api
 */
final readonly class StoreConfig
{
    /**
     * @param non-empty-string $directory Base directory for all stores. A relative path resolves
     *        against the process working directory. Overridable at runtime via `TESTO_STORE_DIR`.
     * @param bool $enabled When `false`, every store is opened in a no-data mode: reads return `null`
     *        and writes are dropped. Owners need no special-casing — the `load(): null` contract
     *        already covers "no data yet".
     */
    public function __construct(
        public string $directory = '.testo/store',
        public bool $enabled = true,
    ) {}
}
