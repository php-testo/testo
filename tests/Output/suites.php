<?php

declare(strict_types=1);

use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Output\Rendering\StackTrace;

return [
    new SuiteConfig(
        name: 'Output/Inline',
        location: new FinderConfig(
            include: [
                \dirname((new \ReflectionClass(StackTrace::class))->getFileName()),
            ],
        ),
    ),
    new SuiteConfig(
        name: 'Output/Unit',
        location: new FinderConfig(
            include: [__DIR__ . '/Unit'],
        ),
    ),
];
