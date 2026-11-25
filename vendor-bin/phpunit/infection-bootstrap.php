<?php

declare(strict_types=1);

/**
 * Included into Infection's own process (the `bootstrap` option of infection.phpunit.json).
 *
 * Infection loads every covered source class to generate its mutants. The Rector bridge rules extend
 * Rector's classes, which live in the isolated vendor-bin/rector vendor rather than the root install, so
 * without this the first covered rule stops the whole run. Rector ships its dependencies scoped,
 * and nikic/php-parser keeps resolving from Infection's vendor. The other bridge vendors carry
 * unscoped symfony/nikic deps that would clash with Infection's, so only Rector is loaded.
 */

$autoload = __DIR__ . '/../rector/vendor/autoload.php';
\is_file($autoload) and require_once $autoload;
