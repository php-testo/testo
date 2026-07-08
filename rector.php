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
        // RepeatInterceptor uses a closure with use (&$symbols) for batched symbol flushing;
        // Rector's deadCode rules incorrectly remove the body as "unused".
        __DIR__ . '/plugin/repeat/src/Internal/RepeatInterceptor.php',
        // DeferredGenerator uses `return $result; yield;` to create a finished generator —
        // a valid PHP trick that Rector converts to an invalid arrow function.
        __DIR__ . '/plugin/data/src/Internal/DeferredGenerator.php',
    ])
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: true,
        typeDeclarations: true,
    );
