<?php

declare(strict_types=1);

namespace Testo\Application\Internal\Messenger;

use Internal\Destroy\Destroyable;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Core\Context\Identity\TestIdentity;
use Testo\Core\Log\Message;
use Testo\Event\Message\MessageReceived;

/**
 * Message buffer of a single {@see \Testo\Common\Messenger} scope or fork.
 *
 * Besides storing the messages recorded within it, the state owns event dispatch: each recorded
 * message is announced via a {@see MessageReceived} event — immediately, or, when the state is a
 * fork opened with `holdEvents`, buffered and released only on {@see commit()} (and discarded if the
 * fork is never committed). A transient suspension flag is an avalanche guard while an event is being
 * dispatched — a listener that logs would otherwise recurse forever. Keeping it here makes it
 * fiber-local for free: scope()/fork() swap the active state across fiber suspensions.
 *
 * A {@see \Testo\Common\Messenger::scope()} buffer is isolated — dropped on exit, never visible to its
 * parent. A {@see \Testo\Common\Messenger::fork()} is a child branch on top of the active state:
 * {@see getMessages()} on a fork reads the parent plus its own (time-ordered) without mutating the
 * parent, and {@see commit()} folds the fork's own messages (and any held events) into the parent.
 *
 * @internal
 */
final class State implements Destroyable
{
    /** @var list<Message> */
    private array $messages = [];

    /**
     * Messages whose {@see MessageReceived} event is buffered (holdEvents mode), pending {@see commit()}.
     *
     * @var list<Message>
     */
    private array $heldEvents = [];

    private bool $suspended = false;

    /**
     * @param TestIdentity|null $identity Test whose {@see MessageReceived} events this state stamps;
     *        `null` when the state belongs to no test. Read by the hub so a nested scope can inherit it.
     */
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ?self $parent = null,
        private readonly bool $holdEvents = false,
        public readonly ?TestIdentity $identity = null,
    ) {}

    /**
     * Record a message: store it, then announce it — right away, or held until {@see commit()} when
     * this state is a holdEvents fork. A no-op while one of this state's events is being dispatched
     * (avalanche guard).
     */
    public function record(Message $message): void
    {
        if ($this->suspended) {
            return;
        }

        $this->messages[] = $message;
        $this->holdEvents ? $this->heldEvents[] = $message : $this->dispatch($message);
    }

    /**
     * @return list<Message>
     */
    public function getMessages(): array
    {
        if ($this->parent === null) {
            return $this->messages;
        }

        $p = clone $this->parent;
        $p->merge($this);
        return $p->getMessages();
    }

    public function fork(bool $holdEvents = false): self
    {
        # A fork belongs to the same test as its parent, so it inherits the parent's identity.
        return new self($this->dispatcher, $this, $holdEvents, $this->identity);
    }

    #[\Override]
    public function destroy(): void
    {
        $this->messages = [];
        $this->heldEvents = [];
    }

    /**
     * Fold this state's messages into its parent, release any held events, and clear its own buffers.
     *
     * Clearing makes the call idempotent — a second {@see commit()} folds nothing — and keeps a
     * post-commit {@see getMessages()} from double-counting (the messages now live in the parent).
     * A no-op on a parentless (root) state: there is nothing to commit into.
     */
    public function commit(): void
    {
        if ($this->parent === null) {
            return;
        }

        $this->parent->merge($this);
        $this->parent->absorbEvents($this->heldEvents);
        $this->messages = [];
        $this->heldEvents = [];
    }

    /**
     * Announce a message via {@see MessageReceived}, guarding against avalanche recursion: a listener
     * that logs while the event is in flight is dropped (the flag is read by {@see record()}).
     */
    private function dispatch(Message $message): void
    {
        $this->suspended = true;
        try {
            $this->dispatcher->dispatch(new MessageReceived($message, $this->identity));
        } finally {
            $this->suspended = false;
        }
    }

    /**
     * Take over the held events of a committing child: dispatch them now, or keep holding them if
     * this state is itself a holdEvents fork (nested forks release only once a non-holding ancestor
     * — eventually the scope — commits).
     *
     * @param list<Message> $events
     */
    private function absorbEvents(array $events): void
    {
        if ($events === []) {
            return;
        }

        if ($this->holdEvents) {
            $this->heldEvents = \array_merge($this->heldEvents, $events);
            # Keep held events in time order so they are released chronologically on commit.
            \usort($this->heldEvents, static fn(Message $a, Message $b) => $a->time <=> $b->time);
            return;
        }

        foreach ($events as $message) {
            $this->dispatch($message);
        }
    }

    /**
     * Fold another state's own buffer into this one, combining the two in time order.
     *
     * Only the given state's own messages are taken — never its merged parent view — otherwise
     * folding a fork (whose {@see getMessages()} re-includes its parent) would recurse forever.
     */
    private function merge(self $state): void
    {
        if ($state->messages === []) {
            return;
        }
        if ($this->messages === []) {
            $this->messages = $state->messages;
            return;
        }

        # Fast path: the incoming buffer starts no earlier than ours ends — just append.
        if ($state->messages[0]->time >= $this->messages[\array_key_last($this->messages)]->time) {
            $this->messages = \array_merge($this->messages, $state->messages);
            return;
        }

        # Out-of-order (clock skew / interleaving): combine and stable-sort by time.
        $merged = \array_merge($this->messages, $state->messages);
        \usort($merged, static fn(Message $a, Message $b) => $a->time <=> $b->time);
        $this->messages = $merged;
    }
}
