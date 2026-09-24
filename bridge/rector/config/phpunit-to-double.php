<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Testo\Bridge\Rector\PhpunitToDouble\CreateMockToDoubleRector;

/**
 * PHPUnit mocks -> Double conversion set.
 *
 * Moves PHPUnit's `createMock()`/`createStub()` doubles and their `expects()/method()/will*()/with()`
 * chains onto Double (`testo/bridge-double`). Pair it with `phpunit-to-testo.php` when migrating a
 * suite, or run it alone. The forms with no faithful Double target are left in place — see
 * bridge/rector/src/PhpunitToDouble/TODO.md.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(CreateMockToDoubleRector::class);
};
