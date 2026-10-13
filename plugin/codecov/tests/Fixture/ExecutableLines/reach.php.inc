<?php
function both(bool $c): int
{
    if ($c) {
        return 1;
    } else {
        return 2;
    }
}
function onlyIf(bool $c): int
{
    if ($c) {
        return 1;
    }
    $x = 2;
}
function chain(int $c): int
{
    if ($c === 1) {
        return 1;
    } elseif ($c === 2) {
        return 2;
    } else {
        throw new \LogicException();
    }
}
function tryReturns(callable $f): mixed
{
    try {
        return $f();
    } finally {
        $done = true;
    }
}
function tryCatchReturns(callable $f): mixed
{
    try {
        return $f();
    } catch (\Throwable $e) {
        return null;
    }
}
function tryCatchFalls(callable $f): void
{
    try {
        $f();
    } catch (\Throwable $e) {
        return;
    }
}
function loopLast(array $a): void
{
    foreach ($a as $v) {
        return;
    }
}
