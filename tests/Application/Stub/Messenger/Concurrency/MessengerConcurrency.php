<?php

declare(strict_types=1);

namespace Tests\Application\Stub\Messenger\Concurrency;

/**
 * Shared channel name for the messenger concurrency stubs and their Feature suite. Not a test case
 * (no {@see \Testo\Test} methods), just the constant both sides agree on.
 */
final class MessengerConcurrency
{
    /** @var non-empty-string */
    public const CHANNEL = 'guard-messenger';
}
