<?php
function numbers(): \Generator
{
    yield 1;
    yield from [2, 3];
    $sent = yield;
    return $sent;
}
