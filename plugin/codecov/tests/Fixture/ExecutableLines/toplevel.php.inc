<?php

declare(strict_types=1);

namespace App\Boot;

const FLAG = true;
\define('APP_BOOT_LOADED', true);

$config = [
    'debug' => false,
    'name' => 'app',
];

if (!\function_exists('App\Boot\helper')) {
    function helper(): int
    {
        return 1;
    }
}

$value = helper();
