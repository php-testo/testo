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
 *   3. for *Test.php files with no faithful PHPUnit form emit a skip-stub instead, so PHPUnit
 *      reports them skipped rather than erroring; support files of those same namespaces are dropped
 *   4. (done by the composer script afterwards) two Rector passes over the copy:
 *      bin/rector-phpunit-rename.php relocates `Tests\` -> `Tests\PhpUnit\` across the whole tree,
 *      then bin/rector-phpunit-build.php converts the *Test.php files to PHPUnit
 *
 * Run via `composer phpunit:build`, not directly.
 */

$root = \str_replace('\\', '/', \dirname(__DIR__));
$dest = $root . '/tests/PhpUnit';

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
$skipped = 0;
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

        // Only whole-file fatals that strike before any test method runs justify a file-level skip
        // stub (a custom constructor or a name colliding with a `final` TestCase method break at
        // class load/instantiation). Per-method reasons — Testo\Testing usage, composite data
        // sources, runtime/layout-bound namespaces — are skipped method-by-method later by
        // SkipUnconvertibleTestMethodRector, so each original test stays visible as its own skip.
        $reason = $isTest ? notConvertibleReason($code) : null;

        if ($reason !== null) {
            \file_put_contents($targetFile, skipStub($namespace, $soleType ?? $file->getBasename('.php'), $reason));
            ++$skipped;
            continue;
        }

        // Content keeps its `Tests\` namespace; the rename Rector pass relocates it afterwards.
        \file_put_contents($targetFile, $code);
        ++$copied;
    }
}

echo "Copied: {$copied}, skip-stubbed: {$skipped}, ignored (Self/helpers): {$ignored}\n";
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
 * filename. Returns null when the file declares zero or several types (multi-type fixtures keep
 * their original filename, since PSR-4 cannot place them anyway).
 */
function soleTypeName(string $code): ?string
{
    return \preg_match_all(
        '/^(?:abstract\s+|final\s+|readonly\s+)*(?:class|interface|trait|enum)\s+([A-Za-z_]\w*)/m',
        $code,
        $m,
    ) === 1 ? $m[1][0] : null;
}

/**
 * Why a *Test.php cannot even be LOADED/instantiated as a PHPUnit TestCase, so the whole file must
 * become a skip stub (these fatals strike before any method runs — a per-method skip cannot help).
 * Returns the reason, or null. Per-method reasons (Testo\Testing usage, composite data sources,
 * runtime/layout-bound namespaces) are handled later by SkipUnconvertibleTestMethodRector.
 */
function notConvertibleReason(string $code): ?string
{
    // PHPUnit owns TestCase's constructor (it injects the method name); a custom __construct leaves
    // $methodName uninitialized and PHPUnit errors while building the test.
    if (\preg_match('/\bfunction\s+__construct\s*\(/', $code) === 1) {
        return 'declares a custom __construct() that conflicts with PHPUnit\\Framework\\TestCase';
    }

    // A method whose name matches a `final` method TestCase inherits (Assert has dozens of final
    // static constraint factories: isIterable(), contains(), equalTo(), ...) is a hard fatal on load.
    $finals = phpUnitFinalMethods();
    if (\preg_match_all('/\bfunction\s+([A-Za-z_]\w*)\s*\(/', $code, $m) > 0) {
        foreach ($m[1] as $method) {
            if (isset($finals[\strtolower($method)])) {
                return "declares a method {$method}() that collides with final PHPUnit\\Framework\\TestCase::{$method}()";
            }
        }
    }

    return null;
}

/**
 * Lowercased set (name => true) of every `final` method TestCase inherits, derived by reflection
 * from the isolated tools/phpunit install — so the denylist tracks the actual PHPUnit version.
 *
 * @return array<string, true>
 */
function phpUnitFinalMethods(): array
{
    static $finals = null;

    if ($finals !== null) {
        return $finals;
    }

    require_once \str_replace('\\', '/', \dirname(__DIR__)) . '/tools/phpunit/vendor/autoload.php';

    $finals = [];
    foreach ((new \ReflectionClass(\PHPUnit\Framework\TestCase::class))->getMethods() as $method) {
        if ($method->isFinal()) {
            $finals[\strtolower($method->getName())] = true;
        }
    }

    return $finals;
}

/** A self-contained PHPUnit test that records exactly one skipped test. */
function skipStub(string $namespace, string $class, string $reason): string
{
    $reason = \addslashes("{$namespace}\\{$class} {$reason}.");

    return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use PHPUnit\\Framework\\TestCase;

        final class {$class} extends TestCase
        {
            public function testNotConvertibleToPhpUnit(): void
            {
                \$this->markTestSkipped('{$reason}');
            }
        }

        PHP;
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
