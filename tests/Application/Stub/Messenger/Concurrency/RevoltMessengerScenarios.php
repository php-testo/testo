<?php

declare(strict_types=1);

namespace Tests\Application\Stub\Messenger\Concurrency;

use Revolt\EventLoop;
use Testo\Assert;
use Testo\Bridge\Revolt\RunInRevolt;
use Testo\Bridge\Revolt\Strategy;
use Testo\Common\Messenger;
use Testo\Core\Log\Message;
use Testo\Test;
use Testo\Testing\Attribute\Inject;

/**
 * The same distinct-messages isolation as {@see RoundRobinMessengerScenarios}, but on a **real Revolt loop**
 * ({@see Strategy::PerCase}): all three tests run at once and interleave at genuine await points (a timer
 * after every logged message). Each test must still read back only its own messages from the shared
 * fiber-local {@see Messenger}, proving the buffer stays per-test through real event-loop scheduling.
 */
#[Test]
#[RunInRevolt(Strategy::PerCase)]
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
     * Log `$count` messages tagged with `$prefix`, awaiting a real timer after each so the other tests
     * interleave on the loop, then assert this test's own buffer holds exactly its own messages.
     */
    private function logAndVerifyOwnMessages(string $prefix, int $count): void
    {
        $expected = [];
        for ($i = 1; $i <= $count; $i++) {
            $content = $prefix . '-' . $i;
            $this->messenger->log(MessengerConcurrency::CHANNEL, $content);
            $expected[] = $content;

            $suspension = EventLoop::getSuspension();
            EventLoop::delay(0.001, static fn() => $suspension->resume());
            $suspension->suspend();
        }

        $mine = \array_map(
            static fn(Message $message): string => $message->content,
            $this->messenger->getMessages()->channel(MessengerConcurrency::CHANNEL),
        );

        Assert::same($mine, $expected);
    }
}
