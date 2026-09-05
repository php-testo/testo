<?php

declare(strict_types=1);

namespace Tests\Test\Stub\Skip;

use Testo\Repeat;
use Testo\Test;
use Testo\Test\Skip;

final class SkipWithRepeatStub
{
    public static bool $bodyRan = false;

    #[Test]
    #[Skip('parked, repeat must not engage')]
    #[Repeat(times: 3)]
    public function parked(): void
    {
        self::$bodyRan = true;
        throw new \LogicException('Must never run: the test is parked.');
    }
}
