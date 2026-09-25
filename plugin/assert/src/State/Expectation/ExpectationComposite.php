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
        $this->records[] = $record = new ExpectationFailed($expectation, $context, $reason, $details);
        $this->message = $this->buildMessage();
        return $record;
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

    /**
     * Reporters print the exception message, so it lists every failed sub-expectation: the bare
     * headline alone reads as if the expected exception was never thrown.
     */
    private function buildMessage(): string
    {
        $reasons = [];
        foreach ($this->records as $record) {
            $record->isSuccess() or $reasons[] = "{$record->getExpectation()}, but {$record->getFailReason()}";
        }

        $message = "Failed expectation that {$this->expectation}.";
        $message .= match (\count($reasons)) {
            0 => '',
            1 => "\nReason: {$reasons[0]}",
            default => "\nReasons:\n- " . \implode("\n- ", $reasons),
        };
        $this->context === '' or $message .= "\nMeaning: {$this->context}";

        return $message;
    }
}
