<?php

declare(strict_types=1);

namespace Testo\ErrorHandler;

/**
 * A single PHP error captured during test execution.
 *
 * @api
 */
final readonly class CapturedError implements \Stringable
{
    /**
     * @param bool $handled The handler installed before the test took the error (returned true), so
     *        PHP would not have printed it.
     */
    public function __construct(
        public int $severity,
        public string $message,
        public string $file,
        public int $line,
        public bool $handled = false,
    ) {}

    /**
     * The error as PHP itself prints it.
     *
     * @return non-empty-string
     */
    #[\Override]
    public function __toString(): string
    {
        return \sprintf('%s: %s in %s on line %d', self::label($this->severity), $this->message, $this->file, $this->line);
    }

    private static function label(int $severity): string
    {
        return match ($severity) {
            \E_WARNING, \E_USER_WARNING, \E_CORE_WARNING, \E_COMPILE_WARNING => 'Warning',
            \E_NOTICE, \E_USER_NOTICE => 'Notice',
            \E_DEPRECATED, \E_USER_DEPRECATED => 'Deprecated',
            \E_RECOVERABLE_ERROR => 'Recoverable fatal error',
            default => 'Error',
        };
    }
}
