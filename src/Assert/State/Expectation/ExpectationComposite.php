<?php

declare(strict_types=1);

namespace Testo\Assert\State\Expectation;

use Testo\Assert\State\Expectation;

/**
 * Composite expectation record that accumulates multiple sub-expectations.
 */
final class ExpectationComposite extends \LogicException implements Expectation
{
    /** @var list<Expectation> */
    private array $records = [];

    private bool $success = true;

    /**
     * @param non-empty-string $expectation The main expectation description.
     */
    public function __construct(
        private readonly string $expectation,
        private readonly string $context = '',
    ) {
        parent::__construct("Failed expectation that {$expectation}.");
    }

    #[\Override]
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @param non-empty-string $expectation The expected condition that was met.
     */
    public function success(string $expectation, string $context = ''): ExpectationFulfilled
    {
        return $this->records[] = new ExpectationFulfilled($expectation, $context);
    }

    /**
     * @param non-empty-string $expectation The expected condition that was not met.
     * @param non-empty-string $reason The reason for the failure.
     */
    public function fail(
        string $expectation,
        string $reason,
        string $context = '',
        string $details = '',
    ): ExpectationFailed {
        $this->success = false;
        return $this->records[] = new ExpectationFailed($expectation, $context, $reason, $details);
    }

    /**
     * @return list<Expectation>
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    #[\Override]
    public function getExpectation(): string
    {
        return $this->expectation;
    }

    #[\Override]
    public function getContext(): string
    {
        return $this->context;
    }

    #[\Override]
    public function getFailReason(): string
    {
        $messages = [];
        foreach ($this->records as $record) {
            if (!$record->isSuccess()) {
                $messages[] = "- {$record->getExpectation()}, but {$record->getFailReason()}";
            }
        }

        if ($messages === []) {
            return '';
        }

        $m = \count($messages) === 1 ? '' : 's';
        return "Failed expectation$m:\n" . \implode("\n", $messages);
    }

    #[\Override]
    public function getFailDetails(): string
    {
        return '';
    }

    #[\Override]
    public function __toString(): string
    {
        $parts = [$this->expectation];
        foreach ($this->records as $record) {
            $parts[] = $record->isSuccess()
                ? $record->getExpectation()
                : "{$record->getExpectation()}, but {$record->getFailReason()}";
        }

        return 'Expected that ' . \implode('; ', $parts) . '.';
    }
}
