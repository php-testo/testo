<?php

declare(strict_types=1);

namespace Testo\Assert\State\Assertion;

use Testo\Assert\State\Assertion;
use Testo\Assert\State\CompositeRecord;
use Testo\Assert\State\Record;

/**
 * Assertion record.
 */
final class AssertionComposite extends AssertionSuccess implements CompositeRecord
{
    /** @var list<Assertion> */
    private array $records = [];

    private bool $success = true;

    #[\Override]
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @param non-empty-string $assertion The assertion (e.g., "greater than 42", "is not empty").
     * @param string $context Optional user-provided context describing what is being asserted.
     */
    public function success(string $assertion, string $context = ''): Record
    {
        return $this->records[] = new AssertionSuccess(
            value: $this->value,
            assertion: $assertion,
            context: $context,
        );
    }

    public function add(Assertion $record): void
    {
        $this->records[] = $record;
        $record->isSuccess() or $this->success = false;
    }

    /**
     * @param non-empty-string $assertion The assertion (e.g., "greater than 42", "is not empty").
     * @param non-empty-string $reason The reason for the assertion failure.
     * @param string $context Optional user-provided context describing what is being asserted.
     * @param string $details The detailed assertion failure information (diff).
     */
    public function fail(
        string $assertion,
        string $reason,
        string $context = '',
        string $details = '',
    ): AssertionException {
        $err = new AssertionException(
            value: $this->value,
            assertion: $assertion,
            context: $context,
            reason: $reason,
            details: $details,
        );

        $this->success = false;
        return $this->records[] = $err;
    }

    #[\Override]
    public function getRecords(): array
    {
        return $this->records;
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
        $messages = [];
        foreach ($this->records as $record) {
            if (!$record->isSuccess()) {
                $messages[] = "- {$record->getAssertion()}, but {$record->getFailReason()}";
            }
        }

        if ($messages === []) {
            return '';
        }

        $m = \count($messages) === 1 ? '' : 's';
        return "Failed assertion$m:\n" . \implode("\n", $messages);
    }

    #[\Override]
    public function __toString(): string
    {
        $parts = [$this->assertion];
        foreach ($this->records as $record) {
            $parts[] = $record->isSuccess()
                ? $record->getAssertion()
                : "{$record->getAssertion()}, but {$record->getFailReason()}";
        }

        return "Assert that `{$this->value}` " . \implode(';  ', $parts) . '.';
    }
}
