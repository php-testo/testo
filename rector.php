<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/core',
        __DIR__ . '/plugin',
        __DIR__ . '/bridge',
    ])
    ->withSkip([
        __DIR__ . '/bridge/rector',
        __DIR__ . '/bridge/symfony-console/resources/stubs',
        __DIR__ . '/bin',
        '*/tests/*',
        '*/Stub/*',
        '*/Fixture/*',
        // Removing unused public-method parameters breaks implementing classes and callers.
        RemoveUnusedPublicMethodParameterRector::class,
    ])
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        typeDeclarations: true,
    );
