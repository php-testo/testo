<?php

declare(strict_types=1);

namespace Testo\ErrorHandler;

/**
 * A single PHP error captured during test execution.
 *
 * @api
 */
final readonly class CapturedError
{
    public function __construct(
        public int $severity,
        public string $message,
        public string $file,
        public int $line,
    ) {}
}
