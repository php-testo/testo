<?php

declare(strict_types=1);

namespace Testo\Assert\Internal;

/**
 * PCRE matching shared by the pattern-based assertions and expectations.
 *
 * @internal
 * @psalm-internal Testo\Assert
 */
final class Pattern
{
    /**
     * Check whether the subject matches the pattern.
     *
     * @param string $pattern Full PCRE pattern with delimiters and flags.
     *
     * @throws \InvalidArgumentException when the pattern is invalid or matching fails.
     */
    public static function matches(string $pattern, string $subject): bool
    {
        # PCRE reports compile errors only through a warning; capture it instead of leaking it.
        $warning = null;
        \set_error_handler(static function (int $errno, string $errstr) use (&$warning): bool {
            $warning = \preg_replace('/^preg_match\(\): /', '', $errstr);
            return true;
        });

        try {
            $result = \preg_match($pattern, $subject);
        } finally {
            \restore_error_handler();
        }

        $result === false and throw new \InvalidArgumentException(\sprintf(
            'Invalid pattern %s: %s',
            $pattern,
            \preg_last_error_msg() . ($warning === null ? '' : ' (' . $warning . ')'),
        ));

        return $result === 1;
    }
}
