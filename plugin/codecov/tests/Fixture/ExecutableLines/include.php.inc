<?php
function load(string $file): mixed
{
    require_once $file;
    include $file;
    return require $file;
}
