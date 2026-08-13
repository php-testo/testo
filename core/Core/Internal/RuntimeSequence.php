<?php

declare(strict_types=1);

namespace Testo\Core\Internal;

/**
 * Process-local monotonic source of runtime ids.
 *
 * Shared by every {@see \Testo\Core\Context\Identity} level so a suite, a case and a test never
 * collide on the same number — one counter, not one per class.
 *
 * @internal
 */
final class RuntimeSequence
{
    /** @var int<0, max> */
    private static int $last = 0;

    /**
     * @return int<1, max>
     */
    public static function next(): int
    {
        return ++self::$last;
    }
}
