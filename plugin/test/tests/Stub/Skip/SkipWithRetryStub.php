<?php

declare(strict_types=1);

namespace Tests\Test\Stub\Skip;

use Testo\Retry;
use Testo\Test;
use Testo\Test\Skip;

final class SkipWithRetryStub
{
    public static int $attempts = 0;

    #[Test]
    #[Skip('parked, retry must not engage')]
    #[Retry(maxAttempts: 3)]
    public function parked(): void
    {
        ++self::$attempts;
        throw new \LogicException('Must never run: the test is parked.');
    }
}
