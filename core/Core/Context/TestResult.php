<?php

declare(strict_types=1);

namespace Testo\Core\Context;

use Testo\Core\Internal\Attributed;
use Testo\Core\Log\MessageLog;
use Testo\Core\Metadata\Metadata;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;

/**
 * @api
 */
final readonly class TestResult
{
    use Attributed;

    /**
     * @param array<non-empty-string, mixed> $attributes
     * @param MessageLog $messages Messages (output and other channels) recorded during the test.
     * @param Summary $summary Statistics of this test (counts, plugin metrics, duration).
     * @param Metadata $metadata Values the test reports beside its status, rendered by every reporter.
     */
    public function __construct(
        public TestInfo $info,
        public Status $status,
        public mixed $result = null,
        public ?\Throwable $failure = null,
        public array $attributes = [],
        public MessageLog $messages = new MessageLog(),
        public Summary $summary = new Summary(),
        public Metadata $metadata = new Metadata(),
    ) {}

    public function with(
        ?Status $status = null,
        ?MessageLog $messages = null,
    ): self {
        return new self(
            info: $this->info,
            status: $status ?? $this->status,
            result: $this->result,
            failure: $this->failure,
            attributes: $this->attributes,
            messages: $messages ?? $this->messages,
            summary: $this->summary,
            metadata: $this->metadata,
        );
    }

    public function withSummary(Summary $summary): self
    {
        return $this->cloneWith('summary', $summary);
    }

    public function withResult(mixed $result): self
    {
        return $this->cloneWith('result', $result);
    }

    public function withFailure(?\Throwable $failure): self
    {
        return $this->cloneWith('failure', $failure);
    }

    public function withMessages(MessageLog $messages): self
    {
        return $this->cloneWith('messages', $messages);
    }

    public function withMetadata(Metadata $metadata): self
    {
        return $this->cloneWith('metadata', $metadata);
    }
}
