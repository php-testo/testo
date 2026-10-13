<?php
function backward(int $n): int
{
    $i = 0;
    again:
    $i++;
    if ($i < $n) {
        goto again;
    }
    return $i;
}

function sameLine(int $n): int
{
    retry: $n--;
    if ($n > 0) goto retry;
    return $n;
}

function outOfLoop(array $items): ?int
{
    foreach ($items as $item) {
        if ($item > 10) {
            goto found;
        }
    }
    return null;
    found:
    return $item;
}

function nested(bool $flag): void
{
    if ($flag) {
        inner:
        echo 'x';
    }
    goto done;
    done:
}
