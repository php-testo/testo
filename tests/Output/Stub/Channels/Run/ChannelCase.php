<?php

declare(strict_types=1);

namespace Tests\Output\Stub\Channels\Run;

use Testo\Assert;
use Testo\Common\Messenger;
use Testo\Test;
use Testo\Testing\Attribute\Inject;

/**
 * A case for the channel-streaming feature tests to run: each test writes one line through `echo` (the
 * `stdout` channel) and one through a channel of its own, so every renderer has both kinds of channel
 * output to show under the right test. One test passes, one fails — the compact JSON report describes
 * failed tests only.
 */
#[Test]
final class ChannelCase
{
    public const CHANNEL = 'custom';

    #[Inject]
    private Messenger $messenger;

    public function writesAndPasses(): void
    {
        echo "printed while passing\n";
        $this->messenger->log(self::CHANNEL, "logged while passing\n");

        Assert::true(true);
    }

    public function writesAndFails(): void
    {
        echo "printed while failing\n";
        $this->messenger->log(self::CHANNEL, "logged while failing\n");

        Assert::fail('deliberate failure');
    }
}
