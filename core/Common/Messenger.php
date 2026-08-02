<?php

declare(strict_types=1);

namespace Testo\Common;

use Testo\Core\Context\Identity\TestIdentity;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Core\Log\MessageLog;
use Testo\Event\Message\MessageReceived;
use Testo\Common\Messenger\Channel;

/**
 * Central message hub.
 *
 * Producers — the output-buffer trap, test code, middleware, or other plugins — write
 * messages into named channels through this service. Every write is recorded into the active
 * scope's buffer and announced via a {@see MessageReceived} event.
 *
 * Buffers are nested per suite / case / test via {@see scope()} — an isolated child that is
 * dropped on exit — while the {@see MessageReceived} event stream stays global. {@see fork()}
 * adds a *mergeable* branch on top of the active scope: its messages fold into the parent only
 * if committed, so a repeated or retried execution can keep one attempt and drop the rest.
 *
 * This interface is meant to be **consumed, not implemented** by userland code: inject or
 * type-hint it to talk to the messenger. The implementation is provided by the framework
 * ({@see \Testo\Application\Internal\MessengerHub}) — new methods may be added in minor releases,
 * which is safe for consumers but would break external implementations.
 *
 * @api
 */
interface Messenger
{
    /**
     * Channel for Testo's own human-facing output — run results, summaries and rendered stack traces.
     *
     * @var non-empty-string
     */
    public const CHANNEL_OUTPUT = 'output';

    /**
     * Channel for native output captured from test code and the pipeline (echo, print, var_dump, ...).
     *
     * @var non-empty-string
     */
    public const CHANNEL_STDOUT = 'stdout';

    /**
     * Channel for internal errors and exceptions that Testo suppresses instead of surfacing as test
     * failures — faulty event listeners, pipeline glitches and other otherwise-swallowed throwables.
     *
     * @var non-empty-string
     */
    public const CHANNEL_STDERR = 'stderr';

    /**
     * Record a message in the given channel.
     *
     * Builds a {@see Message}, stores it in the active scope's buffer and announces it via a
     * {@see MessageReceived} event (the global firehose — fired once per write, regardless of scope).
     *
     * @param non-empty-string $channel
     * @param non-empty-string $content
     * @param array<string, mixed> $context Structured context attached to the message.
     */
    public function log(string $channel, string $content, Level $level = Level::Info, array $context = []): void;

    /**
     * Obtain a channel-bound writer handle.
     *
     * @param non-empty-string $name
     */
    public function channel(string $name): Channel;

    /**
     * Run the given closure within a forked scope.
     *
     * The active state is swapped for a fresh child for the duration of the call and restored
     * afterwards; the child (and its message buffer) is destroyed on exit. {@see getMessages()}
     * inside the closure observes only what was written within it. Fiber-aware: the parent state
     * is restored across suspension points.
     *
     * Every {@see MessageReceived} dispatched from within the scope is stamped with `$identity`, so a
     * consumer can attribute the message to that test even while other tests interleave their output
     * on the same stream. When `$identity` is omitted the enclosing scope's identity carries over,
     * like in {@see fork()}: a scope opened mid-test still belongs to that test. The framework sets
     * the identity once per test (in its per-test output scope) — a plugin rarely needs to pass one.
     *
     * @template T
     * @param \Closure(self): T $scope
     * @param TestIdentity|null $identity Test this scope's messages belong to; inherited from the
     *        enclosing scope when omitted.
     * @return T
     */
    public function scope(\Closure $scope, ?TestIdentity $identity = null): mixed;

    /**
     * Run the given closure within a fork: a mergeable child branch of the active scope.
     *
     * Messages logged while the fork is active accumulate in an isolated child buffer. The closure
     * receives a `$commit` callable that folds the branch's messages into the parent scope; if it is
     * never called, the branch's messages are discarded. `$commit` may be invoked inside the closure
     * or kept and called later — a retried/repeated execution decides whether to keep an attempt only
     * after seeing its result. The active state is restored once the closure returns. Fiber-aware:
     * the parent state is restored across suspension points.
     *
     * Lets a repeated/retried execution stay tentative — commit the attempt whose output should
     * survive, drop the rest — so {@see getMessages()} doesn't accumulate every attempt.
     *
     * With `$holdEvents`, the {@see MessageReceived} events of messages logged inside the fork are
     * buffered instead of dispatched live, and released only on commit (discarded if the fork is
     * dropped). Use it so a discarded attempt stays invisible in real-time consumers too, not just
     * absent from {@see getMessages()}.
     *
     * @template T
     * @param \Closure(callable(): void): T $fork Receives the `$commit` callable.
     * @return T
     */
    public function fork(\Closure $fork, bool $holdEvents = false): mixed;

    /**
     * Messages recorded in the active scope.
     */
    public function getMessages(): MessageLog;
}
