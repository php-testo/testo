<?php

declare(strict_types=1);

namespace Testo\Output\Terminal\Renderer;

/**
 * Color mode for terminal output.
 */
enum ColorMode
{
    /**
     * Auto-detect color support based on terminal capabilities and environment.
     * Checks for:
     * - TERM environment variable
     * - NO_COLOR environment variable
     * - CI environment detection
     * - TTY detection
     */
    case Auto;

    /**
     * Always use colors regardless of terminal capabilities.
     */
    case Always;

    /**
     * Never use colors, plain text output only.
     */
    case Never;

    /**
     * Determines if colors should be enabled based on the mode.
     */
    public function shouldUseColors(): bool
    {
        return match ($this) {
            self::Always => true,
            self::Never => false,
            self::Auto => self::detectColorSupport(),
        };
    }

    /**
     * Auto-detects if terminal supports colors.
     */
    private static function detectColorSupport(): bool
    {
        // Respect NO_COLOR environment variable (https://no-color.org/)
        if (isset($_SERVER['NO_COLOR']) || isset($_ENV['NO_COLOR'])) {
            return false;
        }

        // Check if running in CI without TTY
        if (self::isCI() && !self::isTTY()) {
            return false;
        }

        // Check TERM environment variable
        $term = $_SERVER['TERM'] ?? $_ENV['TERM'] ?? '';
        if ($term === 'dumb') {
            return false;
        }

        // Check if output is to a TTY
        if (self::isTTY()) {
            return true;
        }

        // Default to no colors if can't detect
        return false;
    }

    /**
     * Checks if running in CI environment.
     */
    private static function isCI(): bool
    {
        $ciEnvVars = ['CI', 'CONTINUOUS_INTEGRATION', 'GITHUB_ACTIONS', 'GITLAB_CI', 'CIRCLECI'];

        foreach ($ciEnvVars as $var) {
            if (isset($_SERVER[$var]) || isset($_ENV[$var])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if output is to a TTY (terminal).
     */
    private static function isTTY(): bool
    {
        return \function_exists('posix_isatty') && @posix_isatty(\STDOUT);
    }
}
