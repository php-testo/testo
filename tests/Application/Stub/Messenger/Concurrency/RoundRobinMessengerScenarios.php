<?php

declare(strict_types=1);

namespace Tests\Application\Stub\Messenger\Concurrency;

use Testo\Assert;
use Testo\Common\Messenger;
use Testo\Core\Log\Message;
use Testo\Fiber\RunInFiber;
use Testo\Fiber\Schedule;
use Testo\Test;
use Testo\Testing\Attribute\Inject;

/**
 * Three tests interleaved on Testo's fiber scheduler ({@see Schedule::RoundRobin}), each logging a distinct
 * set of messages on the shared {@see Messenger} across several `\Fiber::suspend()`s. The messenger keeps
 * its per-test buffer guarded across the interleave ({@see \Testo\Application\Internal\MessengerHub}), so each test
 * must read back only its own messages even though a sibling logged into the same {@see Messenger} in
 * between. Every test verifies its own view here; the Feature suite additionally checks each test's
 * captured messages off the {@see \Testo\Core\Context\TestResult}.
 */
#[Test]
#[RunInFiber(Schedule::RoundRobin)]
final class RoundRobinMessengerScenarios
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
     * Log `$count` messages tagged with `$prefix` on the {@see MessengerConcurrency::CHANNEL} channel,
     * suspending after each so the other tests interleave, then assert this test's own message buffer holds
     * exactly its own messages — nothing a sibling logged in between.
     */
    private function logAndVerifyOwnMessages(string $prefix, int $count): void
    {
        $expected = [];
        for ($i = 1; $i <= $count; $i++) {
            $content = $prefix . '-' . $i;
            $this->messenger->log(MessengerConcurrency::CHANNEL, $content);
            $expected[] = $content;
            \Fiber::suspend();
        }

        $mine = \array_map(
            static fn(Message $message): string => $message->content,
            $this->messenger->getMessages()->channel(MessengerConcurrency::CHANNEL),
        );

        Assert::same($mine, $expected);
    }
}
