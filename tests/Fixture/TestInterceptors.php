<?php

declare(strict_types=1);

namespace Tests\Fixture;

use Testo\Attribute\Test;
use Testo\Retry\RetryPolicy;

final class TestInterceptors
{
    #[Test]
    #[RetryPolicy(maxAttempts: 3)]
    public function withRetryPolicy(): int
    {
        static $runs = 0;
        ++$runs < 3 and throw new \RuntimeException("Failed attempt $runs.");
        return $runs;
    }
}
