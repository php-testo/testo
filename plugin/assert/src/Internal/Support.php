<?php

declare(strict_types=1);

namespace Testo\Assert\Internal;

/**
 * @internal
 * @psalm-internal Testo\Assert
 */
final class Support
{
    /**
     * Convert a value to a string for error messages.
     *
     * Compact, single-line representation suitable for inline messages
     * like `Expected `array(3)`, got `array(5)``. Intentionally lossy:
     * arrays are summarised by count, strings >64 chars by length.
     *
     * @return non-empty-string
     */
    public static function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            $value === true => 'true',
            $value === false => 'false',
            \is_string($value) => \strlen($value) > 64
                ? 'string(' . \strlen($value) . ')'
                : '"' . \str_replace('"', '\\"', self::escapeControlChars($value)) . '"',
            \is_array($value) => 'array(' . \count($value) . ')',
            \is_resource($value) => 'resource',
            $value instanceof \UnitEnum => $value::class . '::' . $value->name,
            \is_object($value) => $value::class,
            default => (string) $value,
        };
    }

    /**
     * Spell out the control characters other than tab and line breaks (`\e`, `\x07`, …), so an ANSI
     * escape in an asserted string cannot restyle the terminal that prints the message.
     */
    public static function escapeControlChars(string $value): string
    {
        return (string) \preg_replace_callback(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
            static fn(array $match): string => $match[0] === "\e" ? '\e' : \sprintf('\x%02X', \ord($match[0])),
            $value,
        );
    }

    /**
     * Multi-line, full-content representation of a value, suitable for diff rendering.
     *
     * Unlike {@see self::stringify()}, preserves complete content so that consumers
     * (terminal diff renderer, TeamCity `comparisonFailure`) can produce useful
     * line-by-line diffs.
     *
     * @return non-empty-string
     */
    public static function dump(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            $value === true => 'true',
            $value === false => 'false',
            \is_string($value) => $value === '' ? "''" : $value,
            \is_int($value), \is_float($value) => (string) $value,
            \is_resource($value) => 'resource(' . \get_resource_type($value) . ')',
            $value instanceof \UnitEnum => $value::class . '::' . $value->name,
            \is_array($value), \is_object($value) => \print_r($value, true),
            default => \var_export($value, true),
        };
    }
}
