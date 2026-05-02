<?php

declare(strict_types=1);

namespace Testo\Assert\State\Assertion;

use Testo\Assert\State\Assertion;

/**
 * Assertion exception.
 */
class AssertionException extends \LogicException implements Assertion
{
    /**
     * @param non-empty-string $value The actual value that was asserted.
     * @param non-empty-string $assertion The assertion result.
     * @param string $context Optional user-provided context describing what is being asserted.
     * @param string $reason The reason for the assertion failure.
     * @param string $details The detailed assertion failure information (diff).
     */
    public function __construct(
        private readonly string $value,
        private readonly string $assertion,
        private readonly string $context,
        private readonly string $reason,
        private readonly string $details,
    ) {
        $message = "Failed assertion that `{$value}` {$assertion}: {$reason}.";
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
    public function getValue(): string
    {
        return $this->value;
    }

    #[\Override]
    public function getAssertion(): string
    {
        return $this->assertion;
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
