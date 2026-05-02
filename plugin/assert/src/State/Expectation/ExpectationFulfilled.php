<?php

declare(strict_types=1);

namespace Testo\Assert\State\Expectation;

use Testo\Assert\State\Expectation;

/**
 * Successful assertion record.
 */
class ExpectationFulfilled implements Expectation
{
    /**
     * @param non-empty-string $expectation The assertion result.
     * @param string $context Optional user-provided context describing what is being asserted.
     */
    public function __construct(
        protected readonly string $expectation,
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
    public function getExpectation(): string
    {
        return $this->expectation;
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
        return "Met expectation that {$this->expectation}.";
    }
}
