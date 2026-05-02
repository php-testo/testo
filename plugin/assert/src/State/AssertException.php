<?php

declare(strict_types=1);

namespace Testo\Assert\State;

use Testo\Assert\Internal\Support;
use Testo\Assert\State\Assertion\AssertionException;

/**
 * Assertion exception.
 *
 * @deprecated use {@see AssertionException} instead.
 */
class AssertException extends \LogicException implements Record
{
    /**
     * @param non-empty-string $assertion The assertion result (e.g., "Expected exactly 42, got 43").
     * @param string $context Optional user-provided context describing what is being asserted.
     * @param string $details The detailed assertion failure information (diff).
     */
    final protected function __construct(
        public readonly string $assertion,
        public readonly string $context,
        public readonly string $details,
    ) {
        parent::__construct($this->assertion);
    }

    /**
     * Failed comparison assertion factory.
     *
     * @param mixed $expected The expected value.
     * @param mixed $actual The actual value to compare against the expected value.
     * @param string $message Short description about what exactly is being asserted.
     * @param non-empty-string $pattern The message pattern.
     * @param bool $showDiff Whether to generate a diff between expected and actual values.
     */
    public static function compare(
        mixed $expected,
        mixed $actual,
        string $message,
        string $pattern = 'Expected `%1$s`, got `%2$s`',
        bool $showDiff = true,
    ): self {
        # todo
        $diff = '';

        $msg = \sprintf(
            $pattern,
            Support::stringify($expected),
            Support::stringify($actual),
        );
        return new self(
            assertion: $msg,
            context: $message,
            details: $diff,
        );
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
    final public function __toString(): string
    {
        return $this->assertion;
    }
}
