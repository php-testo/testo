<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Unit\Fixture;

use Testo\Common\Messenger;
use Testo\Common\Messenger\Channel;
use Testo\Core\Context\Identity\TestIdentity;
use Testo\Core\Log\Level;
use Testo\Core\Log\MessageLog;

/**
 * A messenger that hands out channels and keeps whatever is written to them, without emitting it.
 */
final class RecordingMessenger implements Messenger
{
    /** @var list<array{channel: string, content: string, level: Level}> */
    public array $logged = [];

    #[\Override]
    public function log(string $channel, string $content, Level $level = Level::Info, array $context = []): void
    {
        $this->logged[] = ['channel' => $channel, 'content' => $content, 'level' => $level];
    }

    #[\Override]
    public function channel(string $name): Channel
    {
        return new Channel($this, $name);
    }

    #[\Override]
    public function scope(\Closure $scope, ?TestIdentity $identity = null): mixed
    {
        return $scope();
    }

    #[\Override]
    public function fork(\Closure $fork, bool $holdEvents = false): mixed
    {
        return $fork();
    }

    #[\Override]
    public function getMessages(): MessageLog
    {
        return new MessageLog();
    }
}
