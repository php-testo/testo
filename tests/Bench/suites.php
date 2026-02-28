<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Bench\BenchWith;

return [
    new SuiteConfig(
        name: 'Bench/Inline',
        location: new FinderConfig(
            include: [
                dirname((new \ReflectionClass(BenchWith::class))->getFileName()),
            ],
        ),
    ),
    new SuiteConfig(
        name: 'Bench/Self',
        location: new FinderConfig(
            include: [__DIR__ . '/Self'],
        ),
    ),
];
