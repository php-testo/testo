<?php

declare(strict_types=1);

/**
 * Shared autoload bootstrap for every runner over Testo's own suite: the Testo CLI (testo.php),
 * the generated PHPUnit mirror (phpunit.xml) and mutation testing on top of either.
 *
 * It loads the root autoloader, then the isolated bamarni-bin vendors under tools/ that hold the
 * bridge runtime deps kept out of the root install — a higher PHP floor (double) or heavy/foreign
 * transitive deps (vcr, mockery, revolt, rector). The is_file() guard keeps a run working when a
 * bin namespace was not installed (double on PHP 8.2, a job that skipped it); the bridge's own
 * suites.php still gates itself on the platform it needs.
 *
 * Only bridge vendors are listed. The standalone dev tools (psalm, phpunit, infection, code-style)
 * also live under tools/, but their nikic/symfony deps differ by version and would clash if loaded
 * into the same process as the tests.
 */

require_once __DIR__ . '/../vendor/autoload.php';

foreach (['double', 'vcr', 'mockery', 'revolt', 'rector'] as $binNamespace) {
    $autoload = __DIR__ . "/../tools/{$binNamespace}/vendor/autoload.php";
    \is_file($autoload) and require_once $autoload;
}
