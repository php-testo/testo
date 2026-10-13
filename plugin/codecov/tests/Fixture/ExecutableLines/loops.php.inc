<?php
function loops(array $items): int
{
    $sum = 0;
    foreach ($items as $key => $value) {
        $sum += $value;
    }
    for ($i = 0; $i < 3; $i++) {
        continue;
    }
    while ($sum > 100) {
        $sum--;
    }
    do {
        $sum++;
    } while ($sum < 0);
    return $sum;
}
