<?php

declare(strict_types=1);

namespace Tests\Bench\Self;

use Testo\Bench\BenchWith;

final class BenchWithAttr
{
    #[BenchWith([
        [self::class, 'sumSlow'],
    ], arguments: [1, 2])]
    public static function sumFast(int $a, int $b): int
    {
        return $a + $b;
    }

    public static function sumSlow(int $a, int $b): int
    {
        return (int) \array_sum([$a, $b]);
    }
}
