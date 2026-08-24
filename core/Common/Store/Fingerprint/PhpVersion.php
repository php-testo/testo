<?php

declare(strict_types=1);

namespace Testo\Common\Store\Fingerprint;

use Testo\Common\Store\FingerprintContributor;

/**
 * Invalidates a store when the PHP minor version changes. Patch releases are ignored: they do not
 * change tokenization or observable coverage, so re-recording on every patch bump would be waste.
 *
 * @api
 */
final readonly class PhpVersion implements FingerprintContributor
{
    #[\Override]
    public function key(): string
    {
        return 'php';
    }

    #[\Override]
    public function value(): string
    {
        return \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION;
    }
}
