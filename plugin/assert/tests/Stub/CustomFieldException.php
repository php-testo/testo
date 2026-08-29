<?php

declare(strict_types=1);

namespace Tests\Assert\Stub;

/**
 * Exception carrying custom fields beyond message/code — mirrors the shape from issue #265.
 * Used to characterize how {@see \Testo\Expect::exception()} treats custom properties today.
 */
final class CustomFieldException extends \Exception
{
    public function __construct(
        string $message = '',
        public readonly string $details = '',
        private readonly int $severity = 0,
    ) {
        parent::__construct($message);
    }

    public function getSeverity(): int
    {
        return $this->severity;
    }
}
