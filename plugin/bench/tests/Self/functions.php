<?php

declare(strict_types=1);

use Testo\Bench;

function viaDivision(int $a, int $b): int
{
    $d = $b - $a + 1;
    return (int) (($d - 1) * $d / 2) + $a * $d;
}

function viaMultiply(int $a, int $b): int
{
    $d = $b - $a + 1;
    return (int) (($d - 1) * $d * 0.5) + $a * $d;
}

#[Bench(
    callables: [
        'division' => 'viaDivision',
        'multiply' => 'viaMultiply',
    ],
    arguments: [1, 200],
    calls: 2_000,
    tolerance: \INF,
)]
function viaShift(int $a, int $b): int
{
    $d = $b - $a + 1;
    return ((($d - 1) * $d) >> 1) + $a * $d;
}
