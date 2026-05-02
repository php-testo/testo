<?php

declare(strict_types=1);

namespace Testo\Assert\State\Expectation;

use Testo\Assert\State\Expectation;

/**
 * Exception representing a failed expectation.
 */
class ExpectationFailed extends \LogicException implements Expectation
{
    /**
     * @param non-empty-string $expectation The expected condition that was not met.
     * @param string $context Optional user-provided context describing the expectation.
     * @param string $reason The reason for the expectation failure.
     * @param string $details Optional detailed information about the failure.
     */
    public function __construct(
        private readonly string $expectation,
        private readonly string $context,
        private readonly string $reason,
        private readonly string $details,
    ) {
        $message = "Failed expectation that {$expectation}.";
        $reason === '' or $message .= "\nReason: {$reason}";
        $context === '' or $message .= "\nMeaning: {$context}";
        $details === '' or $message .= "\nDetails:\n{$details}";

        parent::__construct($message);
    }

    #[\Override]
    final public function isSuccess(): bool
    {
        return false;
    }

    #[\Override]
    final public function getContext(): string
    {
        return $this->context;
    }

    #[\Override]
    public function getExpectation(): string
    {
        return $this->expectation;
    }

    #[\Override]
    public function getFailReason(): string
    {
        return $this->reason;
    }

    #[\Override]
    public function getFailDetails(): string
    {
        return $this->details;
    }

    #[\Override]
    final public function __toString(): string
    {
        return $this->getMessage();
    }
}
