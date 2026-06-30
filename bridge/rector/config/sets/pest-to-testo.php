<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Testo\Bridge\Rector\PestToTesto\ExpectToAssertRector;

/**
 * Pest -> Testo conversion set.
 *
 * This direction is LARGELY MANUAL. Pest is a functional DSL (file-level
 * `test()`/`it()` closures, `expect()`, `beforeEach()`, `uses()`), while Testo is
 * class + attribute based. Restructuring file-level test closures into Testo
 * classes/methods with correct naming, `$this` binding, and lifecycle cannot be
 * done reliably by Rector (it rewrites AST in place; it does not synthesize the
 * enclosing class). See bridge/rector/src/PestToTesto/TODO.md.
 *
 * Only the genuinely-tractable rule is registered: {@see ExpectToAssertRector},
 * which maps a single `expect($v)->toX(...)` expectation to `\Testo\Assert::*`.
 * Every other Pest construct is a documented, unregistered stub.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(ExpectToAssertRector::class);
};
