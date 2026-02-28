<?php

declare(strict_types=1);

namespace Testo\Common;

/**
 * Facade for accessing environment information.
 */
final class Environment
{
    private static string $phpVersion;
    private static string $os;
    private static string $cpu;
    private static bool $xDebugExists;
    private static ?string $xDebugVersion;
    private static bool $opCacheEnabled;
    private static bool $jitEnabled;

    private function __construct() {}

    public static function getPhpVersion(): string
    {
        self::init();
        return self::$phpVersion;
    }

    public static function getOs(): string
    {
        self::init();
        return self::$os;
    }

    public static function getCpu(): string
    {
        self::init();
        return self::$cpu;
    }

    public static function hasXDebug(): bool
    {
        self::init();
        return self::$xDebugExists;
    }

    /**
     * Get XDebug modes if XDebug is available, otherwise return an empty array.
     *
     * @return list<non-empty-string>
     */
    public static function getXDebugMode(): array
    {
        self::init();
        return self::$xDebugExists && \function_exists('xdebug_info')
            ? xdebug_info('mode')
            : [];
    }

    public static function getXDebugVersion(): ?string
    {
        self::init();
        return self::$xDebugVersion;
    }

    public static function isOpCacheEnabled(): bool
    {
        self::init();
        return self::$opCacheEnabled;
    }

    public static function isJitEnabled(): bool
    {
        self::init();
        return self::$jitEnabled;
    }

    private static function init(): void
    {
        static $skip = false;
        if ($skip) {
            return;
        }

        $skip = true;
        self::$phpVersion = \phpversion();
        self::$os = \php_uname('s') . ' ' . \php_uname('r');
        self::$cpu = \php_uname('m');
        self::$xDebugExists = \extension_loaded('xdebug');
        self::$xDebugVersion = self::$xDebugExists ? \phpversion('xdebug') : null;
        self::$opCacheEnabled = \extension_loaded('Zend OPcache') && \ini_get('opcache.enable_cli') === '1';
        self::$jitEnabled = self::$opCacheEnabled && ((int) \ini_get('opcache.jit_buffer_size')) > 0;
    }
}
