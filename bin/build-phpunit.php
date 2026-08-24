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

    // The mirror base this whole root maps to: $dest for the core `tests/` root (its layout already
    // mirrors `Tests\PhpUnit\`), or the base derived from a sample test file's namespace for a
    // plugin/bridge root (e.g. bridge/vcr/tests -> $dest/Bridge/VCR). Support data is mirrored
    // relative to it so a fixture read by `__DIR__/../fixtures` still resolves.
    $rootBase = mirrorBaseDir($srcRoot, $coreTestsRoot, $dest);

    /** @var \SplFileInfo $file */
    foreach (new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($srcRoot, \FilesystemIterator::SKIP_DOTS),
    ) as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $path = \str_replace('\\', '/', $file->getPathname());

        // Never re-scan our own output.
        if (\str_starts_with($path, $dest . '/')) {
            continue;
        }
        // Skip Self-tests: framework fixtures driven by meta-tests, not standalone unit tests.
        if (\str_contains($path, '/Self/')) {
            ++$ignored;
            continue;
        }

        // Support data (a Stub/Fixture/fixtures directory): the tests that use it tokenize it — and
        // often `require` it — by RELATIVE PATH, so it must be mirrored verbatim next to the relocated
        // tests, under its original filename. This covers every root for NON-PHP support files (a
        // fixture, a VCR cassette, a `*.php.inc`, and a `.gitkeep` keeping an intentionally EMPTY stub
        // dir — e.g. an empty suite location — alive), plus PHP stubs in the core `tests/` root, which
        // are read/`require`d by path so must keep their name (the namespace-based placement below
        // would rename them via soleTypeName()). A PHP stub CLASS in a plugin/bridge root instead
        // falls through to the namespace-based placement, which its PSR-4 autoload relies on.
        $isSupport = \preg_match('#/(?:stub|fixture)s?/#i', $path) === 1;
        if ($isSupport && ($file->getExtension() !== 'php' || $srcRoot === $coreTestsRoot)) {
            $relative = \ltrim(\substr($path, \strlen($srcRoot)), '/');
            $targetFile = $rootBase . '/' . $relative;
            @\mkdir(\dirname($targetFile), 0777, true);
            \copy($path, $targetFile);
            ++$copied;
            continue;
        }

        // Beyond support data, only namespaced PHP test/helper files are placed.
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $code = \file_get_contents($path);

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

/**
 * The mirror base directory a whole source root maps to. The core `tests/` root maps straight to
 * $dest; a plugin/bridge root's base is read off a sample namespaced test file — its `Tests\…`
 * namespace maps to a `$dest/…` path, and stripping the file's own subdirectory within the root
 * leaves the base the root maps to (e.g. bridge/vcr/tests -> $dest/Bridge/VCR). Falls back to $dest
 * when the root holds no namespaced test file.
 */
function mirrorBaseDir(string $srcRoot, string $coreTestsRoot, string $dest): string
{
    if ($srcRoot === $coreTestsRoot) {
        return $dest;
    }

    /** @var \SplFileInfo $file */
    foreach (new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($srcRoot, \FilesystemIterator::SKIP_DOTS),
    ) as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $namespace = extractNamespace((string) \file_get_contents($file->getPathname()));
        if ($namespace === null) {
            continue;
        }

        $nsPath = \trim(\substr($namespace, \strlen('Tests')), '\\');
        $nsPath = $nsPath === '' ? '' : \str_replace('\\', '/', $nsPath);

        $filePath = \str_replace('\\', '/', $file->getPathname());
        $fileRelDir = \trim(\substr(\dirname($filePath), \strlen($srcRoot)), '/');

        // The namespace path ends with the file's own subdirectory when the layout is regular; strip
        // it to leave what the root itself maps to.
        if ($fileRelDir !== '' && \str_ends_with($nsPath, $fileRelDir)) {
            $nsPath = \rtrim(\substr($nsPath, 0, -\strlen($fileRelDir)), '/');
        }

        return $nsPath === '' ? $dest : $dest . '/' . $nsPath;
    }

    return $dest;
}

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
