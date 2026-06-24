<?php

declare(strict_types=1);

namespace Testo\Core\Log;

/**
 * Immutable collection of {@see Message}s recorded during a single test.
 *
 * The read-only counterpart of a producer's mutable message buffer: once a test finishes, its
 * messages are snapshotted into a {@see MessageLog} and carried by {@see \Testo\Core\Context\TestResult}.
 *
 * @implements \IteratorAggregate<int, Message>
 * @psalm-immutable
 * @api
 */
final readonly class MessageLog implements \IteratorAggregate, \Countable
{
    /** @var list<Message> */
    private array $messages;

    /**
     * @param Message[] $messages Normalized to a list, preserving insertion order.
     */
    public function __construct(array $messages = [])
    {
        $this->messages = \array_values($messages);
    }

    /**
     * @return list<Message>
     */
    public function all(): array
    {
        return $this->messages;
    }

    /**
     * Messages belonging to the given channel, in recorded order.
     *
     * @param non-empty-string $channel
     * @return list<Message>
     */
    public function channel(string $channel): array
    {
        return \array_values(\array_filter(
            $this->messages,
            static fn(Message $message): bool => $message->channel === $channel,
        ));
    }

    /**
     * Messages with the given severity level, in recorded order.
     *
     * @return list<Message>
     */
    public function level(Level $level): array
    {
        return \array_values(\array_filter(
            $this->messages,
            static fn(Message $message): bool => $message->level === $level,
        ));
    }

    public function isEmpty(): bool
    {
        return $this->messages === [];
    }

    #[\Override]
    public function count(): int
    {
        return \count($this->messages);
    }

    /**
     * @return \Traversable<int, Message>
     */
    #[\Override]
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->messages);
    }
}
