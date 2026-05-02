<?php

declare(strict_types=1);

namespace Testo\Assert\State\Assertion;

use Testo\Assert\State\Assertion;

/**
 * Successful assertion record.
 */
class AssertionSuccess implements Assertion
{
    /**
     * @param non-empty-string $value The actual value that was asserted.
     * @param non-empty-string $assertion The assertion result.
     * @param string $context Optional user-provided context describing what is being asserted.
     */
    public function __construct(
        protected readonly string $value,
        protected readonly string $assertion,
        protected readonly string $context,
    ) {}

    #[\Override]
    public function isSuccess(): bool
    {
        return true;
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
        return '';
    }

    #[\Override]
    public function getFailDetails(): string
    {
        return '';
    }

    #[\Override]
    public function __toString(): string
    {
        return "Successful assertion that `{$this->value}` {$this->assertion}.";
    }
}
