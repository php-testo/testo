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
                : '"' . \str_replace('"', '\\"', $value) . '"',
            \is_array($value) => 'array(' . \count($value) . ')',
            \is_resource($value) => 'resource',
            \is_object($value) => $value::class,
            default => (string) $value,
        };
    }
}
