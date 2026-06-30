<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Testo\PhpUnitBuild\Rector\RelocateTestsNamespaceRector;

require_once __DIR__ . '/Rector/RelocateTestsNamespaceRector.php';

/**
 * First Rector pass of `composer phpunit:build`: relocate `Tests\` -> `Tests\PhpUnit\` across the
 * WHOLE generated mirror (tests + their support/fixture/stub classes). Rename-only, so it is safe
 * on the odd multi-namespace fixture files that the conversion pass deliberately skips. The
 * conversion pass (bin/rector-phpunit-build.php) runs afterwards over the *Test.php files only.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__ . '/../tests/PhpUnit']);

    $rectorConfig->rule(RelocateTestsNamespaceRector::class);
};
