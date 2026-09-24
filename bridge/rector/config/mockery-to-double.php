<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Testo\Bridge\Rector\MockeryToDouble\MockeryIntegrationToDoubleRector;
use Testo\Bridge\Rector\MockeryToDouble\MockeryToDoubleRector;

/**
 * Mockery -> Double conversion set.
 *
 * Moves Mockery doubles — `mock()`/`spy()`, `shouldReceive()`/`allows()`/`expects()` chains,
 * `shouldHaveReceived()` verification and `Mockery::close()` — onto Double (`testo/bridge-double`),
 * following Double's Mockery migration table. Independent of the test framework, so it runs on PHPUnit
 * and Testo suites alike. The forms with no faithful Double target are left in place — see
 * bridge/rector/src/MockeryToDouble/TODO.md.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(MockeryToDoubleRector::class);

    # A PHPUnit class verifying through Mockery's integration verifies through Double's instead.
    $rectorConfig->rule(MockeryIntegrationToDoubleRector::class);
};
