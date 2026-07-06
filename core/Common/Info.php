<?php

declare(strict_types=1);

namespace Testo\Common;

/**
 * Application basic information provider.
 *
 * This class provides access to essential application metadata such as name, version, and paths.
 *
 * @internal
 */
final class Info
{
    /** @var non-empty-string Application name */
    public const NAME = 'Testo';

    /** @var non-empty-string Absolute path to the root directory */
    public const ROOT_DIR = __DIR__ . '/../..';

    /** @var non-empty-string Default version identifier if version file is not available */
    private const VERSION = 'experimental';

    /**
     * Returns the current application version.
     *
     * Version is retrieved from version.json file or falls back to the default value.
     * Results are cached for subsequent calls.
     *
     * @return non-empty-string The application version string
     */
    public static function version(): string
    {
        /** @var non-empty-string|null $cache */
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $fileContent = \file_get_contents(self::ROOT_DIR . '/resources/version.json');

        if ($fileContent === false) {
            return $cache = self::VERSION;
        }

        $version = \json_decode($fileContent, true)['.'] ?? null;

        return $cache = \is_string($version) && $version !== ''
            ? $version
            : self::VERSION;
    }
}
