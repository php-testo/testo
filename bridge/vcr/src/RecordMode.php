<?php

declare(strict_types=1);

namespace Testo\Bridge\VCR;

/**
 * How PHP-VCR treats the cassette while a {@see \Testo\Bridge\VCR}-tagged test runs.
 *
 * The backing value is the exact string PHP-VCR's `Configuration::setMode()` expects, so mapping is a
 * plain `->value`.
 *
 * @api
 */
enum RecordMode: string
{
    /**
     * Replay interactions already on the cassette; record any request not yet on it (making a real
     * call for it). PHP-VCR's default.
     */
    case NewEpisodes = 'new_episodes';

    /**
     * Record every interaction on the first run (when the cassette file does not exist yet), then
     * replay-only on subsequent runs.
     */
    case Once = 'once';

    /**
     * Replay only; a request with no recorded match throws. Best for CI — guarantees no test ever
     * touches the network.
     */
    case None = 'none';
}
