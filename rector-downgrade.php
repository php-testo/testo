<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DowngradePhp82\Rector\Class_\DowngradeReadonlyClassRector;

/**
 * Downgrade the 8.2-only syntax used by the codebase (`readonly class`) to its 8.1
 * equivalent (per-property `readonly`), so the suite can be exercised on PHP 8.1 in CI.
 *
 * The source itself stays on 8.2; this is applied in place only inside the 8.1 job,
 * right before the tests run. `readonly class` is the sole 8.2-exclusive construct here:
 * the container keeps the affected services shared across scopes via the explicit
 * {@see \Internal\Container\Attribute\ScopeShared} attribute once the class-level
 * `readonly` (and its implicit reflection-based sharing) is gone.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/core',
        __DIR__ . '/plugin',
        __DIR__ . '/bridge',
        __DIR__ . '/tests',
        __DIR__ . '/testo.php',
    ])
    ->withSkip([
        // Resource stubs are templates, not loaded classes, and use newer syntax on purpose.
        __DIR__ . '/bridge/symfony-console/resources/stubs',
        // Rector rule fixtures carry intentional before/after snippets.
        '*.php.inc',
    ])
    ->withRules([
        DowngradeReadonlyClassRector::class,
    ]);
