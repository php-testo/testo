<?php

declare(strict_types=1);

namespace Testo\Application\Internal;

use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Internal\Messenger\State;
use Testo\Application\Internal\Messenger\MutableContainer;
use Testo\Common\Messenger;
use Testo\Common\Messenger\Channel;
use Testo\Core\Context\Identity\TestIdentity;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Core\Log\MessageLog;

/**
 * Default {@see Messenger} implementation.
 *
 * State is held in a separate {@see State} object and swapped during {@see scope()}, mirroring
 * the state/scope model of {@see \Internal\Container\ObjectContainer}: each scope owns an
 * isolated message buffer, while the {@see MessageReceived} event stream stays global.
 *
 * @internal
 */
final readonly class MessengerHub implements Messenger
{
    private MutableContainer $state;

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
        $this->state = new MutableContainer(new State($this->eventDispatcher));
    }

    #[\Override]
    public function log(string $channel, string $content, Level $level = Level::Info, array $context = []): void
    {
        // The active state records the message and announces it (or holds the event, in a holdEvents
        // fork, until commit); it also guards against avalanche recursion during dispatch.
        /** @psalm-suppress ArgumentTypeCoercion */
        $this->state->state->record(new Message(\microtime(true), $channel, $level, $content, $context));
    }

    #[\Override]
    public function channel(string $name): Channel
    {
        return new Channel($this, $name);
    }

    #[\Override]
    public function scope(\Closure $scope, ?TestIdentity $identity = null): mixed
    {
        $old = $this->state->state;
        // A test scope carries the test's identity, so every MessageReceived dispatched from within it
        // is stamped with that test — the seam that keeps interleaving tests' output attributable.
        // With no identity given the ambient one carries over, like in fork(): a nested scope opened
        // mid-test still belongs to that test, and stamping null would silently strip attribution.
        $new = new State($this->eventDispatcher, identity: $identity ?? $old->identity);
        try {
            $this->state->state = $new;
            if (\Fiber::getCurrent() === null) {
                return $scope($this);
            }

            // Wrap scope into a fiber so the parent state is restored across suspensions.
            $self = $this;
            $fiber = new \Fiber(static fn() => $scope($self));
            $value = $fiber->start();
            while (!$fiber->isTerminated()) {
                $this->state->state = $old;
                try {
                    $resume = \Fiber::suspend($value);
                } catch (\Throwable $e) {
                    $this->state->state = $new;
                    $value = $fiber->throw($e);
                    continue;
                }

                $this->state->state = $new;
                $value = $fiber->resume($resume);
            }

            return $fiber->getReturn();
        } finally {
            $this->state->state = $old;
            $new->destroy();
        }
    }

    #[\Override]
    public function fork(\Closure $fork, bool $holdEvents = false): mixed
    {
        $old = $this->state->state;
        $new = $old->fork($holdEvents);
        try {
            $this->state->state = $new;
            if (\Fiber::getCurrent() === null) {
                return $fork($new->commit(...));
            }

            // Wrap the fork into a fiber so the parent state is restored across suspensions.
            $fiber = new \Fiber(static fn() => $fork($new->commit(...)));
            $value = $fiber->start();
            while (!$fiber->isTerminated()) {
                $this->state->state = $old;
                try {
                    $resume = \Fiber::suspend($value);
                } catch (\Throwable $e) {
                    $this->state->state = $new;
                    $value = $fiber->throw($e);
                    continue;
                }

                $this->state->state = $new;
                $value = $fiber->resume($resume);
            }

            return $fiber->getReturn();
        } finally {
            // Restore the parent, but do NOT destroy the fork: the `$commit` callable may be invoked
            // after this returns (the caller decides to keep/drop the branch only once it sees the
            // result). commit() clears the fork's own buffer; an abandoned fork is freed by GC.
            $this->state->state = $old;
        }
    }

    #[\Override]
    public function getMessages(): MessageLog
    {
        return new MessageLog($this->state->state->getMessages());
    }
}
