<?php

declare(strict_types=1);

namespace Testo\Application\Internal;

use Psr\EventDispatcher\EventDispatcherInterface;
use Internal\Fiber\FiberLocal;
use Testo\Application\Internal\Messenger\State;
use Testo\Common\Messenger;
use Testo\Common\Messenger\Channel;
use Testo\Core\Context\TestIdentity;
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
 * The active {@see State} is kept in a {@see FiberLocal} so a scope entered inside a test fiber stays
 * isolated to that fiber: while several tests interleave on one event loop, each reads its own buffer
 * across every switch, with no swapping at the suspension boundary.
 *
 * @internal
 */
final readonly class MessengerHub implements Messenger
{
    /** @var FiberLocal<State> */
    private FiberLocal $current;

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
        $this->current = new FiberLocal(new State($this->eventDispatcher));
    }

    #[\Override]
    public function log(string $channel, string $content, Level $level = Level::Info, array $context = []): void
    {
        // The active state records the message and announces it (or holds the event, in a holdEvents
        // fork, until commit); it also guards against avalanche recursion during dispatch.
        $this->current->get()->record(new Message(\microtime(true), $channel, $level, $content, $context));
    }

    #[\Override]
    public function channel(string $name): Channel
    {
        return new Channel($this, $name);
    }

    #[\Override]
    public function scope(\Closure $scope, ?TestIdentity $identity = null): mixed
    {
        // A test scope carries the test's identity, so every MessageReceived dispatched from within it
        // is stamped with that test — the seam that keeps interleaving tests' output attributable.
        $new = new State($this->eventDispatcher, identity: $identity);
        return $this->current->scope($new, fn(): mixed => $scope($this), $new->destroy(...));
    }

    #[\Override]
    public function fork(\Closure $fork, bool $holdEvents = false): mixed
    {
        // A fork is a child branch on top of the active state. No destroy: the `$commit` callable may be
        // invoked after this returns (the caller keeps/drops the branch once it sees the result); commit()
        // clears the fork's own buffer, and an abandoned fork is freed by GC.
        $new = $this->current->get()->fork($holdEvents);
        return $this->current->scope($new, static fn(): mixed => $fork($new->commit(...)));
    }

    #[\Override]
    public function getMessages(): MessageLog
    {
        return new MessageLog($this->current->get()->getMessages());
    }
}
