<?php

declare(strict_types=1);

namespace Testo\ErrorHandler\Internal;

use Testo\ErrorHandler\CapturedError;

/**
 * Collection of PHP errors accumulated during test execution.
 *
 * Stored as a {@see \Testo\Core\Context\TestResult} attribute under the key {@see CapturedErrors::class}.
 * Renderers that wish to display collected errors should retrieve it from the result.
 *
 * @internal
 * @psalm-internal Testo\ErrorHandler
 */
final readonly class CapturedErrors
{
    /** @param list<CapturedError> $errors */
    public function __construct(
        public array $errors,
    ) {}

    public function isEmpty(): bool
    {
        return $this->errors === [];
    }
}
