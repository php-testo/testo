<?php

declare(strict_types=1);

namespace Testo\Codecov\Result;

/**
 * Represents the coverage status of a single source code line.
 */
enum LineStatus: int
{
    /**
     * Line was executed during tests.
     */
    case Executed = 1;

    /**
     * Line is executable but was not executed.
     */
    case NotExecuted = -1;

    /**
     * Line is not executable (dead code, comments, declarations).
     */
    case Dead = -2;

    public function isExecutable(): bool
    {
        return $this !== self::Dead;
    }
}
