<?php

declare(strict_types=1);

namespace Testo\Output\Html\Internal;

/**
 * Renders a value the way a data set's arguments are read rather than the way they are stored.
 *
 * A report has to state what a test was called with, and the arguments are arbitrary PHP: closures,
 * resources, objects with recursive references, strings the size of a fixture file. Nothing here tries
 * to be a serializer — the point is a short, honest label. Long strings are cut, nested structures stop
 * at a shallow depth, and anything without a readable form answers with its type.
 *
 * @internal
 */
final class ValuePrinter
{
    /** Longest string rendered in full; anything longer is cut and marked. */
    private const STRING_LIMIT = 120;

    /** How deep into arrays and objects to look before answering with the type alone. */
    private const MAX_DEPTH = 2;

    private function __construct() {}

    /**
     * Type of a value as a reader expects to see it — `string`, `int`, `array`, or a class name.
     *
     * @return non-empty-string
     */
    public static function type(mixed $value): string
    {
        return \get_debug_type($value);
    }

    public static function print(mixed $value, int $depth = 0): string
    {
        return match (true) {
            $value === null => 'null',
            $value === true => 'true',
            $value === false => 'false',
            \is_int($value), \is_float($value) => (string) $value,
            \is_string($value) => self::string($value),
            \is_array($value) => self::array($value, $depth),
            $value instanceof \UnitEnum => '\\' . $value::class . '::' . $value->name,
            \is_object($value) => self::object($value, $depth),
            \is_resource($value) => 'resource(' . \get_resource_type($value) . ')',
            default => \get_debug_type($value),
        };
    }

    /**
     * @return non-empty-string
     */
    private static function string(string $value): string
    {
        $cut = \mb_strlen($value) > self::STRING_LIMIT;
        $cut and $value = \mb_substr($value, 0, self::STRING_LIMIT);

        # Control characters would break a single-line label; the escapes read as the source would write
        # them, which is what a reader comparing against the test file needs.
        $escaped = \str_replace(
            ["\\", "'", "\n", "\r", "\t"],
            ['\\\\', "\\'", '\n', '\r', '\t'],
            $value,
        );

        return "'" . $escaped . ($cut ? "…'" : "'");
    }

    /**
     * @param array<array-key, mixed> $value
     * @return non-empty-string
     */
    private static function array(array $value, int $depth): string
    {
        if ($value === []) {
            return '[]';
        }

        if ($depth >= self::MAX_DEPTH) {
            return 'array(' . \count($value) . ')';
        }

        $isList = \array_is_list($value);
        $parts = [];
        $shown = 0;
        foreach ($value as $key => $item) {
            if ($shown === 5) {
                $parts[] = '…';
                break;
            }

            $parts[] = $isList
                ? self::print($item, $depth + 1)
                : self::print($key, $depth + 1) . ' => ' . self::print($item, $depth + 1);
            ++$shown;
        }

        return '[' . \implode(', ', $parts) . ']';
    }

    /**
     * @return non-empty-string
     */
    private static function object(object $value, int $depth): string
    {
        $class = '\\' . $value::class;

        # A value object's readable form is what it says about itself; anything else is named by its
        # class, because dumping arbitrary state into a report is neither short nor safe.
        if ($value instanceof \Stringable) {
            return $depth >= self::MAX_DEPTH ? $class : $class . '(' . self::string((string) $value) . ')';
        }

        if ($value instanceof \DateTimeInterface) {
            return $class . '(' . $value->format(\DATE_ATOM) . ')';
        }

        return $class;
    }
}
