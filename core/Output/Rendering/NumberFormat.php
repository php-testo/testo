<?php

declare(strict_types=1);

namespace Testo\Output\Rendering;

/**
 * Numbers as text for protocols that carry them as strings.
 *
 * @internal
 */
final class NumberFormat
{
    private function __construct() {}

    /**
     * An integer keeps its exact form; a float is written in fixed notation with trailing zeros
     * trimmed, so a near-zero value reads as `0.000001` rather than as `1.0E-6`, which a JUnit consumer
     * or a TeamCity `type='number'` metric would have to special-case.
     */
    public static function fixed(int|float $value): string
    {
        return \is_int($value)
            ? (string) $value
            : \rtrim(\rtrim(\sprintf('%.6F', $value), '0'), '.');
    }
}
