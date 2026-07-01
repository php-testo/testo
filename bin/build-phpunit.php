<?php

declare(strict_types=1);

/**
 * Regenerates the tests/PhpUnit/ mirror of Testo's own test suite for mutation testing.
 *
 * Pipeline (see bridge/rector/README.md "Why Testo -> PHPUnit"):
 *   1. wipe tests/PhpUnit/
 *   2. copy every test *.php EXCEPT files under a Self/ directory, placing each file at the path
 *      its FUTURE `Tests\PhpUnit\...` namespace implies (derived from its current `Tests\...` one);
 *      the file content keeps its original `Tests\` namespace here
 *   3. (done by the composer script afterwards) two Rector passes over the copy:
 *      bin/rector-phpunit-rename.php relocates `Tests\` -> `Tests\PhpUnit\` across the whole tree,
 *      then bin/rector-phpunit-build.php converts the *Test.php files to PHPUnit (including the
 *      per-method skips for tests with no faithful PHPUnit form)
 *
 * Run via `composer phpunit:build`, not directly.
 */

$root = \str_replace('\\', '/', \dirname(__DIR__));
$dest = $root . '/tests/PhpUnit';
$coreTestsRoot = $root . '/tests';

/** Source roots that hold Testo's own tests. */
$roots = \array_merge(
    [$root . '/tests'],
    \glob($root . '/plugin/*/tests') ?: [],
    \glob($root . '/bridge/*/tests') ?: [],
);

echo "Cleaning {$dest}\n";
rrmdir($dest);
@\mkdir($dest, 0777, true);

$copied = 0;
$ignored = 0;

foreach ($roots as $srcRoot) {
    $srcRoot = \str_replace('\\', '/', $srcRoot);

    /** @var \SplFileInfo $file */
    foreach (new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($srcRoot, \FilesystemIterator::SKIP_DOTS),
    ) as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = \str_replace('\\', '/', $file->getPathname());

        // Skip Self-tests: framework fixtures driven by meta-tests, not standalone unit tests.
        if (\str_contains($path, '/Self/')) {
            ++$ignored;
            continue;
        }
        // Never re-scan our own output.
        if (\str_starts_with($path, $dest . '/')) {
            continue;
        }

        $code = \file_get_contents($path);

        // Support data (Stub/ or Fixture/ directory) in the core `tests/` root: the tests that use it
        // tokenize it — and often `require` it — by RELATIVE PATH, so it must be mirrored verbatim at
        // the same relative location under $dest, with its original filename. The namespace-based
        // placement below would misplace it: its namespace may be irregular (braced, repeated,
        // sub-namespaced or global) or its filename may differ from its sole class, and soleTypeName()
        // would rename it out from under the `require`/read. Copied as-is; the rename Rector pass still
        // relocates any `Tests\` namespace it carries. Restricted to the core `tests/` root, whose
        // layout already mirrors the `Tests\PhpUnit\` PSR-4 path (so autoloading a well-behaved stub
        // still resolves); plugin/bridge roots, where source layout and namespace diverge, keep the
        // namespace-based placement below.
        if ($srcRoot === $coreTestsRoot && \preg_match('#/(?:Stub|Fixture)s?/#', $path) === 1) {
            $relative = \ltrim(\substr($path, \strlen($srcRoot)), '/');
            $targetFile = $dest . '/' . $relative;
            @\mkdir(\dirname($targetFile), 0777, true);
            \file_put_contents($targetFile, $code);
            ++$copied;
            continue;
        }

        $namespace = extractNamespace($code);

        // Only namespaced PSR-4 classes are placeable; bare helper files (functions.php) are loaded
        // by the root autoloader's `files` map and need no copy.
        if ($namespace === null) {
            ++$ignored;
            continue;
        }

        $isTest = \str_ends_with($file->getBasename(), 'Test.php');

        $relativeNs = \trim(\substr($namespace, \strlen('Tests')), '\\'); // drop leading "Tests"
        $relativeDir = $relativeNs === '' ? '' : \str_replace('\\', '/', $relativeNs) . '/';
        $targetDir = $dest . '/' . $relativeDir;

        // Place the file under its CLASS name, not the source filename: Testo discovers tests by its
        // own locator and tolerates filename != classname, but the PHPUnit mirror autoloads via PSR-4
        // and must match. Only for single-type files; multi-type fixtures keep their source name.
        $soleType = soleTypeName($code);
        $basename = $soleType !== null ? $soleType . '.php' : $file->getBasename();
        $targetFile = $targetDir . $basename;

        @\mkdir($targetDir, 0777, true);

        // Content keeps its `Tests\` namespace; the rename Rector pass relocates it afterwards.
        // Everything convertible is handled by the Rector passes — a parameterless constructor
        // becomes setUp(), a method colliding with a final TestCase method is renamed, and tests
        // with no faithful PHPUnit form are skipped per method. No file-level skip stub is needed.
        \file_put_contents($targetFile, $code);
        ++$copied;
    }
}

echo "Copied: {$copied}, ignored (Self/helpers): {$ignored}\n";
echo "Next: rector + composer dump-autoload (run by the composer script).\n";

/** Extract the file's namespace (expected to start with `Tests`), or null when there is none. */
function extractNamespace(string $code): ?string
{
    return \preg_match('/^\s*namespace\s+(Tests(?:\\\\[A-Za-z0-9_]+)*)\s*[;{]/m', $code, $m) === 1
        ? \trim($m[1])
        : null;
}

/**
 * The name of the file's sole type declaration (class/interface/trait/enum), used as the PSR-4
 * filename. Returns null when the file declares zero or several types (so multi-type fixtures keep
 * their original filename, since PSR-4 cannot place them anyway), OR when the file also declares
 * free functions — such a file is loaded by path (`require`), not autoloaded, so renaming it would
 * break the `require` (e.g. a stub of test functions that also defines one helper class).
 */
function soleTypeName(string $code): ?string
{
    // A top-level `function` (column 0, unlike an indented method) means the file is require()d.
    if (\preg_match('/^function\s+\w+\s*\(/m', $code) === 1) {
        return null;
    }

    return \preg_match_all(
        '/^(?:abstract\s+|final\s+|readonly\s+)*(?:class|interface|trait|enum)\s+([A-Za-z_]\w*)/m',
        $code,
        $m,
    ) === 1 ? $m[1][0] : null;
}

function rrmdir(string $dir): void
{
    if (!\is_dir($dir)) {
        return;
    }

    /** @var \SplFileInfo $file */
    foreach (new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST,
    ) as $file) {
        $file->isDir() ? @\rmdir($file->getPathname()) : @\unlink($file->getPathname());
    }

    @\rmdir($dir);
}
