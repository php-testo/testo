<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Testo\Bridge\Rector\TestoToPhpunit\AssertCallToPhpUnitRector;

/**
 * Testo -> PHPUnit conversion set.
 *
 * Primary use case: run mutation testing with a runner (PHPUnit) that shares no
 * code with the mutated engine, so mutating Testo's own pipeline cannot break
 * test discovery. See bridge/rector/README.md.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(AssertCallToPhpUnitRector::class);
};
