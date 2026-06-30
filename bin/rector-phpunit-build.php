<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Testo\PhpUnitBuild\Rector\SkipUnconvertibleTestMethodRector;

require_once __DIR__ . '/Rector/SkipUnconvertibleTestMethodRector.php';

/**
 * Rector config used by `composer phpunit:build` over the generated tests/PhpUnit/ mirror.
 *
 * Runs the public Testo -> PHPUnit conversion set, then SkipUnconvertibleTestMethodRector marks the
 * individual test methods that cannot run under PHPUnit as skipped (registered last so it sees the
 * `#[Test]` attributes the conversion adds). The `Tests\` -> `Tests\PhpUnit\` relocation already
 * happened in the rename pass (bin/rector-phpunit-rename.php).
 */
return static function (RectorConfig $rectorConfig): void {
    // Convert ONLY the test classes (*Test.php). Support/stub/fixture classes are not tests; they
    // just need their already-renamed `Tests\PhpUnit\` namespace to autoload — their leftover Testo
    // attributes are inert under PHPUnit. Skipping them also avoids Rector choking on the odd
    // multi-class / multi-namespace fixture file.
    $rectorConfig->paths(collectTestFiles(__DIR__ . '/../tests/PhpUnit'));

    $rectorConfig->import(__DIR__ . '/../bridge/rector/config/sets/testo-to-phpunit.php');

    $rectorConfig->rule(SkipUnconvertibleTestMethodRector::class);
};

/**
 * @return list<string> absolute paths of every *Test.php under $dir
 */
function collectTestFiles(string $dir): array
{
    if (!\is_dir($dir)) {
        return [];
    }

    $files = [];

    /** @var \SplFileInfo $file */
    foreach (new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
    ) as $file) {
        $path = \str_replace('\\', '/', $file->getPathname());

        // Fixture/Stub classes are support data, not tests — even when their name ends in `Test`.
        // Converting them would, e.g., turn the Testo attributes a test reflects on into PHPUnit
        // ones; leave them as-is (only their namespace was relocated by the build script).
        if (\str_contains($path, '/Fixture/') || \str_contains($path, '/Stub/')) {
            continue;
        }

        if ($file->isFile() && \str_ends_with($file->getBasename(), 'Test.php')) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}
