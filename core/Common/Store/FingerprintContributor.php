<?php

declare(strict_types=1);

namespace Testo\Common\Store;

/**
 * One contribution to a store's environment fingerprint.
 *
 * The fingerprint is captured once per opened store — on its first read or write — and compared
 * against what was recorded on the last {@see Store::save()}. Any divergence makes the stored payload
 * count as absent, so a store never serves data captured under conditions that no longer hold (a
 * different PHP version, a changed lockfile, a swapped coverage driver). Contributors describe the
 * environment, which is stable within a run, so {@see value()} is not re-evaluated per call.
 *
 * @api
 */
interface FingerprintContributor
{
    /**
     * Stable identifier of this contribution, unique within one fingerprint: `php`, `composer.lock`.
     *
     * @return non-empty-string
     */
    public function key(): string;

    /**
     * Current value to compare against the recorded one: a version, a content hash, a marker.
     *
     * @return non-empty-string
     */
    public function value(): string;
}
