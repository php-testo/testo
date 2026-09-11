<?php

declare(strict_types=1);

// This config lives under tools/code-style/, next to its isolated php-cs-fixer install: the
// autoloader sits alongside it, and the __DIR__-based source paths reach the project root with ../../.
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Expand wildcard path patterns into the concrete directories they match.
 *
 * Builder::include() takes one existing directory and silently ignores anything that is not one, so
 * glob roots like `plugin/<name>/src` must be resolved to real directory paths before being added.
 *
 * @return list<string>
 */
function expandWildcardDirs(string ...$patterns): array
{
    $dirs = [];

    foreach ($patterns as $pattern) {
        foreach (\glob($pattern, \GLOB_ONLYDIR) ?: [] as $dir) {
            $dirs[] = $dir;
        }
    }

    return $dirs;
}

$builder = \Spiral\CodeStyle\Builder::create()
    ->include(__DIR__ . '/../../core');

foreach (expandWildcardDirs(__DIR__ . '/../../plugin/*/src', __DIR__ . '/../../bridge/*/src') as $dir) {
    $builder->include($dir);
}

return $builder
    ->include(__FILE__)
    // Keep the cache in the root runtime/ (git-ignored); the default is relative to this config's
    // own directory, which would otherwise litter tools/code-style/ with an untracked cache file.
    ->cache(__DIR__ . '/../../runtime/php-cs-fixer.cache')
    ->build();
