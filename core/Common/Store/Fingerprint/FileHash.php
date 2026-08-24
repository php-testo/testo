<?php

declare(strict_types=1);

namespace Testo\Common\Store\Fingerprint;

use Internal\Path;
use Testo\Common\Store\FingerprintContributor;

/**
 * Invalidates a store when a file's content changes. Typical targets: `composer.lock` (dependency
 * versions), `testo.php` (run configuration). A relative path resolves against the process working
 * directory, like every other path Testo reads.
 *
 * @api
 */
final readonly class FileHash implements FingerprintContributor
{
    private Path $path;

    public function __construct(Path|string $path)
    {
        $this->path = Path::create($path);
    }

    #[\Override]
    public function key(): string
    {
        return (string) $this->path;
    }

    #[\Override]
    public function value(): string
    {
        $file = (string) $this->path;
        # A missing file is a stable, distinct value: its appearance or removal is itself a change.
        if (!\is_file($file)) {
            return 'absent';
        }

        $hash = @\hash_file('xxh128', $file);

        return $hash === false ? 'unreadable' : 'xxh128:' . $hash;
    }
}
