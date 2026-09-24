<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Testo\Bridge\Rector\PhpunitToMockery\CreateMockToMockeryRector;

/**
 * PHPUnit mocks -> Mockery conversion set.
 *
 * Moves PHPUnit's `createMock()`/`createStub()` doubles and their `expects()/method()/will*()/with()`
 * chains onto Mockery, verified after every test by `testo/bridge-mockery`. Pair it with
 * `phpunit-to-testo.php` when migrating a suite, or run it alone. The forms with no faithful Mockery
 * target are left in place — see bridge/rector/src/PhpunitToMockery/TODO.md.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(CreateMockToMockeryRector::class);
};
