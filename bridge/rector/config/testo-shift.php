<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\MethodCall\RenameMethodRector;
use Rector\Renaming\ValueObject\MethodCallRename;

/**
 * Testo -> Testo shift set: moves code off deprecated Testo API onto its replacement.
 *
 * Every Testo deprecation adds its migration here, so running this set before upgrading past the
 * removal keeps a test suite compiling. Each entry is behaviour-preserving: the deprecated API is
 * an alias of the one it is renamed to. The fixtures next to this file, in `testo-shift/`, cover
 * every entry.
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->ruleWithConfiguration(RenameMethodRector::class, [
        new MethodCallRename('Testo\\Assert\\Api\\ExpectedException', 'withMessagePattern', 'withMessageMatchingRegex'),
    ]);
};
