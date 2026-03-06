<?php

declare(strict_types=1);

namespace Testo\Output\Terminal\Renderer;

/**
 * Terminal styling utilities with configurable color support.
 *
 * @internal
 */
final class Style
{
    private static bool $colorsEnabled = true;

    private function __construct() {}

    /**
     * Configures color support globally.
     */
    public static function setColorsEnabled(bool $enabled): void
    {
        self::$colorsEnabled = $enabled;
    }

    /**
     * Checks if colors are currently enabled.
     */
    public static function areColorsEnabled(): bool
    {
        return self::$colorsEnabled;
    }

    /**
     * Colorizes text with the given color.
     *
     * @param non-empty-string $text
     */
    public static function colorize(string $text, Color $color): string
    {
        return self::$colorsEnabled
            ? $color->value . $text . Color::Reset->value
            : $text;
    }

    /**
     * Makes text bold.
     *
     * @param non-empty-string $text
     */
    public static function bold(string $text): string
    {
        return self::$colorsEnabled
            ? Color::Bold->value . $text . Color::Reset->value
            : $text;
    }

    /**
     * Makes text dim (less visible).
     *
     * @param non-empty-string $text
     */
    public static function dim(string $text): string
    {
        return self::$colorsEnabled
            ? Color::Dim->value . $text . Color::Reset->value
            : $text;
    }

    /**
     * Creates a banner with background color.
     *
     * @param non-empty-string $text
     */
    public static function banner(string $text, Color $bg, Color $fg = Color::White): string
    {
        return self::$colorsEnabled
            ? $fg->value . $bg->value . Color::Bold->value . " {$text} " . Color::Reset->value
            : " {$text} ";
    }

    /**
     * Formats success text (green).
     *
     * @param non-empty-string $text
     */
    public static function success(string $text): string
    {
        return self::colorize($text, Color::Green);
    }

    /**
     * Formats error text (red).
     *
     * @param non-empty-string $text
     */
    public static function error(string $text): string
    {
        return self::colorize($text, Color::Red);
    }

    /**
     * Formats warning text (yellow).
     *
     * @param non-empty-string $text
     */
    public static function warning(string $text): string
    {
        return self::colorize($text, Color::Yellow);
    }

    /**
     * Formats info text (cyan).
     *
     * @param non-empty-string $text
     */
    public static function info(string $text): string
    {
        return self::colorize($text, Color::Cyan);
    }
}
