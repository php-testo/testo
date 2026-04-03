<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal\Driver;

use Internal\Path;

/**
 * Normalizes {@see Path} to system-native directory prefix for path matching.
 *
 * @internal
 */
trait NormalizePath
{
    /**
     * Converts a {@see Path} to a system-native string with trailing separator.
     *
     * @return non-empty-string
     */
    private static function normalizePath(Path $path): string
    {
        $p = \str_replace('/', \DIRECTORY_SEPARATOR, (string) $path->absolute());

        return $path->isDir() ? $p . \DIRECTORY_SEPARATOR : $p;
    }
}
