<?php

declare(strict_types=1);

namespace Tests\Application\Stub\Messenger\Concurrency;

use Revolt\EventLoop;
use Testo\Assert;
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Common\Messenger;
use Testo\Core\Log\Message;
use Testo\Test;
use Testo\Testing\Attribute\Inject;

/**
 * Message buffering on a **real Revolt loop**, where {@see RunInRevolt} gives the whole loop run to one test
 * at a time. Each test logs a distinct number of messages, awaiting a genuine timer after each, and writes
 * the last one from a **coroutine of its own** that it starts on the loop.
 *
 * The messenger scope is opened outside the loop and never suspends, so for the whole dispatch the active
 * buffer is this test's — and the spawned coroutine, which holds no scope of its own, writes into exactly
 * that. Reading the buffer back must then show this test's messages, in order, the coroutine's included.
 * Keeping interleaved tests' buffers apart is {@see RoundRobinMessengerScenarios}' job, on fibers Testo
 * drives itself.
 */
#[Test]
#[RunInRevolt]
final class RevoltMessengerScenarios
{
    #[Inject]
    private Messenger $messenger;

    public function alphaLogsThreeMessages(): void
    {
        $this->logAndVerifyOwnMessages('alpha', 3);
    }

    public function betaLogsFourMessages(): void
    {
        $this->logAndVerifyOwnMessages('beta', 4);
    }

    public function gammaLogsFiveMessages(): void
    {
        $this->logAndVerifyOwnMessages('gamma', 5);
    }

    /**
     * Log `$count` messages tagged with `$prefix` — all but the last from the test body, awaiting a real
     * timer after each, the last from a spawned coroutine — then assert the buffer holds exactly them.
     */
    private function logAndVerifyOwnMessages(string $prefix, int $count): void
    {
        $expected = [];
        for ($i = 1; $i < $count; $i++) {
            $content = $prefix . '-' . $i;
            $this->messenger->log(MessengerConcurrency::CHANNEL, $content);
            $expected[] = $content;

            $suspension = EventLoop::getSuspension();
            EventLoop::delay(0.001, static fn() => $suspension->resume());
            $suspension->suspend();
        }

        $last = $prefix . '-' . $count;
        $expected[] = $last;
        $this->inCoroutine(fn() => $this->messenger->log(MessengerConcurrency::CHANNEL, $last));

        $mine = \array_map(
            static fn(Message $message): string => $message->content,
            $this->messenger->getMessages()->channel(MessengerConcurrency::CHANNEL),
        );

        Assert::same($mine, $expected);
    }

    /**
     * Run $body as a coroutine of its own on the loop and block until it finishes.
     */
    private function inCoroutine(\Closure $body): void
    {
        $suspension = EventLoop::getSuspension();

        EventLoop::queue(static function () use ($suspension, $body): void {
            try {
                $body();
                $suspension->resume();
            } catch (\Throwable $e) {
                $suspension->throw($e);
            }
        });

        $suspension->suspend();
    }
}
