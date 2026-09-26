<?php

declare(strict_types=1);

/**
 * Survey test files for everything that still needs an AI/human decision, and write batched
 * work-lists for the subagent porting pass. Used by both migration paths:
 *   - Rector path: run it AFTER Rector to find the structural residue Rector cannot convert
 *     (PHPUnit attributes and functions, mocks, constraints, tests inherited from a vendor base, …).
 *   - AI-only path: run it FIRST to plan the file-by-file port.
 *
 * Usage:
 *   php scan-residuals.php --scope=DIR [--scope=DIR]... [--out=DIR] [--batch=N] [--root=PATH] [--no-autoload]
 *
 * --scope=DIR     Directory to scan (repeatable). Default: tests, test (whichever exist).
 * --out=DIR       Where to write the report + batches (default: `runtime` if it exists, else `build`).
 * --batch=N       Files per batch work-list (default 8).
 * --root=PATH     Project root (default: cwd).
 * --no-autoload   Never load the project's `vendor/autoload.php`. By default it is loaded (only when a
 *                 class extends a parent declared outside the scope) to check whether that parent is
 *                 still a PHPUnit TestCase; without it the check falls back to a name heuristic.
 *
 * Writes:
 *   <out>/migration-report.md            ranked summary + batch index (read this first)
 *   <out>/migration-batches/NNN.json     per-file work-lists for the subagent pass
 *
 * Each batch entry: { path, tests, needs[], hints{} } where needs[] is the ordered to-do list.
 * Files whose only findings are low-severity cleanup (an unused `use PHPUnit\…` import) are listed
 * in the report but not batched: they need no subagent.
 * Exit codes: 0 ok, 1 usage, 2 no files found.
 */

$scopes = [];
$out = null;
$batch = 8;
$root = null;
$autoload = true;
foreach (\array_slice($argv, 1) as $arg) {
    if (\preg_match('/^--scope=(.+)$/', $arg, $m)) {
        $scopes[] = \rtrim($m[1], "/\\");
    } elseif (\preg_match('/^--out=(.+)$/', $arg, $m)) {
        $out = \rtrim($m[1], "/\\");
    } elseif (\preg_match('/^--batch=(\d+)$/', $arg, $m)) {
        $batch = \max(1, (int) $m[1]);
    } elseif (\preg_match('/^--root=(.+)$/', $arg, $m)) {
        $root = $m[1];
    } elseif ($arg === '--no-autoload') {
        $autoload = false;
    } else {
        \fwrite(\STDERR, "unknown argument: {$arg}\n");
    }
}

$root = \rtrim($root ?? \getcwd(), "/\\");

if ($scopes === []) {
    foreach (['tests', 'test'] as $candidate) {
        \is_dir($root . '/' . $candidate) and $scopes[] = $candidate;
    }
}
if ($scopes === []) {
    \fwrite(\STDERR, "No scope. Pass --scope=DIR.\n");
    exit(1);
}

if ($out === null) {
    $out = \is_dir($root . '/runtime') ? 'runtime' : 'build';
}
$outAbs = $root . '/' . $out;
\is_dir($outAbs) or @\mkdir($outAbs, 0o777, true);
$batchDir = $outAbs . '/migration-batches';
\is_dir($batchDir) or @\mkdir($batchDir, 0o777, true);

const PHPUNIT_TEST_CASE = 'phpunit\framework\testcase';
const PHPUNIT_TEST_ATTR = 'phpunit\framework\attributes\test';
const TESTO_TEST_ATTR = 'testo\test';

/**
 * PHPUnit's own assertion-style methods (`PHPUnit\Framework\Assert` public statics, PHPUnit 9-12, plus
 * `fail`/`markTestSkipped`). A `$this->assertX()` outside this list is a project helper, not residue.
 * `assertThat`, `markTestIncomplete` and `expect*` have their own checks.
 */
const PHPUNIT_ASSERTS = [
    'assertArrayHasKey', 'assertArrayNotHasKey', 'assertArrayIsEqualToArrayOnlyConsideringListOfKeys',
    'assertArrayIsEqualToArrayIgnoringListOfKeys', 'assertArrayIsIdenticalToArrayOnlyConsideringListOfKeys',
    'assertArrayIsIdenticalToArrayIgnoringListOfKeys', 'assertArraySubset', 'assertIsList',
    'assertContains', 'assertContainsEquals', 'assertNotContains', 'assertNotContainsEquals',
    'assertContainsOnly', 'assertContainsOnlyArray', 'assertContainsOnlyBool', 'assertContainsOnlyCallable',
    'assertContainsOnlyFloat', 'assertContainsOnlyInt', 'assertContainsOnlyIterable', 'assertContainsOnlyNull',
    'assertContainsOnlyNumeric', 'assertContainsOnlyObject', 'assertContainsOnlyResource',
    'assertContainsOnlyClosedResource', 'assertContainsOnlyScalar', 'assertContainsOnlyString',
    'assertContainsOnlyInstancesOf', 'assertNotContainsOnly', 'assertContainsNotOnlyArray',
    'assertContainsNotOnlyBool', 'assertContainsNotOnlyCallable', 'assertContainsNotOnlyFloat',
    'assertContainsNotOnlyInt', 'assertContainsNotOnlyIterable', 'assertContainsNotOnlyNull',
    'assertContainsNotOnlyNumeric', 'assertContainsNotOnlyObject', 'assertContainsNotOnlyResource',
    'assertContainsNotOnlyClosedResource', 'assertContainsNotOnlyScalar', 'assertContainsNotOnlyString',
    'assertContainsNotOnlyInstancesOf', 'assertCount', 'assertNotCount', 'assertSameSize', 'assertNotSameSize',
    'assertEquals', 'assertEqualsCanonicalizing', 'assertEqualsIgnoringCase', 'assertEqualsWithDelta',
    'assertNotEquals', 'assertNotEqualsCanonicalizing', 'assertNotEqualsIgnoringCase', 'assertNotEqualsWithDelta',
    'assertObjectEquals', 'assertObjectNotEquals', 'assertEmpty', 'assertNotEmpty',
    'assertGreaterThan', 'assertGreaterThanOrEqual', 'assertLessThan', 'assertLessThanOrEqual',
    'assertFileEquals', 'assertFileEqualsCanonicalizing', 'assertFileEqualsIgnoringCase', 'assertFileNotEquals',
    'assertFileNotEqualsCanonicalizing', 'assertFileNotEqualsIgnoringCase', 'assertStringEqualsFile',
    'assertStringEqualsFileCanonicalizing', 'assertStringEqualsFileIgnoringCase', 'assertStringNotEqualsFile',
    'assertStringNotEqualsFileCanonicalizing', 'assertStringNotEqualsFileIgnoringCase',
    'assertIsReadable', 'assertIsNotReadable', 'assertNotIsReadable', 'assertIsWritable', 'assertIsNotWritable',
    'assertNotIsWritable', 'assertDirectoryExists', 'assertDirectoryDoesNotExist', 'assertDirectoryNotExists',
    'assertDirectoryIsReadable', 'assertDirectoryIsNotReadable', 'assertDirectoryNotIsReadable',
    'assertDirectoryIsWritable', 'assertDirectoryIsNotWritable', 'assertDirectoryNotIsWritable',
    'assertFileExists', 'assertFileDoesNotExist', 'assertFileNotExists', 'assertFileIsReadable',
    'assertFileIsNotReadable', 'assertFileNotIsReadable', 'assertFileIsWritable', 'assertFileIsNotWritable',
    'assertFileNotIsWritable', 'assertTrue', 'assertNotTrue', 'assertFalse', 'assertNotFalse',
    'assertNull', 'assertNotNull', 'assertFinite', 'assertInfinite', 'assertNan',
    'assertObjectHasProperty', 'assertObjectNotHasProperty', 'assertObjectHasAttribute',
    'assertObjectNotHasAttribute', 'assertClassHasAttribute', 'assertClassNotHasAttribute',
    'assertClassHasStaticAttribute', 'assertClassNotHasStaticAttribute',
    'assertSame', 'assertNotSame', 'assertInstanceOf', 'assertNotInstanceOf',
    'assertIsArray', 'assertIsBool', 'assertIsFloat', 'assertIsInt', 'assertIsNumeric', 'assertIsObject',
    'assertIsResource', 'assertIsClosedResource', 'assertIsString', 'assertIsScalar', 'assertIsCallable',
    'assertIsIterable', 'assertIsNotArray', 'assertIsNotBool', 'assertIsNotFloat', 'assertIsNotInt',
    'assertIsNotNumeric', 'assertIsNotObject', 'assertIsNotResource', 'assertIsNotClosedResource',
    'assertIsNotString', 'assertIsNotScalar', 'assertIsNotCallable', 'assertIsNotIterable',
    'assertMatchesRegularExpression', 'assertDoesNotMatchRegularExpression', 'assertRegExp', 'assertNotRegExp',
    'assertStringMatchesFormat', 'assertStringNotMatchesFormat', 'assertStringMatchesFormatFile',
    'assertStringNotMatchesFormatFile', 'assertFileMatchesFormat', 'assertFileMatchesFormatFile',
    'assertStringStartsWith', 'assertStringStartsNotWith', 'assertStringEndsWith', 'assertStringEndsNotWith',
    'assertStringContainsString', 'assertStringContainsStringIgnoringCase', 'assertStringNotContainsString',
    'assertStringNotContainsStringIgnoringCase', 'assertStringContainsStringIgnoringLineEndings',
    'assertStringEqualsStringIgnoringLineEndings', 'assertXmlFileEqualsXmlFile', 'assertXmlFileNotEqualsXmlFile',
    'assertXmlStringEqualsXmlFile', 'assertXmlStringNotEqualsXmlFile', 'assertXmlStringEqualsXmlString',
    'assertXmlStringNotEqualsXmlString', 'assertEqualXMLStructure', 'assertJson',
    'assertJsonStringEqualsJsonString', 'assertJsonStringNotEqualsJsonString', 'assertJsonStringEqualsJsonFile',
    'assertJsonStringNotEqualsJsonFile', 'assertJsonFileEqualsJsonFile', 'assertJsonFileNotEqualsJsonFile',
    'fail', 'markTestSkipped',
];

/**
 * Token-level outline of one file: its class-likes (resolved parent, attributes, methods), its
 * `use PHPUnit\…` class imports and whether code uses them, and every name that resolves into the
 * `PHPUnit\` namespace, tagged by where it occurs.
 *
 * Names are FQCNs without the leading backslash, in source case; attribute names are lower-cased.
 *
 * @return array{
 *     classes: list<array{kind:string, name:string, parent:?string, abstract:bool, attrs:list<string>,
 *         methods:list<array{name:string, public:bool, abstract:bool, attrs:list<string>,
 *             parentCalls:list<string>}>}>,
 *     imports: array<string, array{fqcn:string, used:bool}>,
 *     refs: list<array{name:string, kind:string}>,
 * }
 */
function outline(string $code): array
{
    $isName = static fn(\PhpToken $x): bool => $x->is([\T_STRING, \T_NAME_QUALIFIED, \T_NAME_FULLY_QUALIFIED, \T_NAME_RELATIVE]);
    $t = \array_values(\array_filter(
        \PhpToken::tokenize($code),
        static fn(\PhpToken $x): bool => !$x->isIgnorable(),
    ));
    $n = \count($t);

    $ns = '';
    $classUses = [];
    $funcUses = [];
    $imports = [];
    $refs = [];
    $classes = [];
    $stack = [];
    $depth = 0;
    $attrs = [];
    $mods = [];
    $pendingMethod = null;
    $currentMethod = null;

    $resolve = static function (string $name, bool $function = false) use (&$ns, &$classUses, &$funcUses, &$imports): string {
        if ($name[0] === '\\') {
            return \substr($name, 1);
        }
        if (\str_starts_with(\strtolower($name), 'namespace\\')) {
            return \ltrim($ns . '\\' . \substr($name, 10), '\\');
        }
        $pos = \strpos($name, '\\');
        if ($function && $pos === false) {
            return $funcUses[\strtolower($name)] ?? \ltrim($ns . '\\' . $name, '\\');
        }
        $first = \strtolower($pos === false ? $name : \substr($name, 0, $pos));
        if (isset($classUses[$first])) {
            isset($imports[$first]) and $imports[$first]['used'] = true;
            return $classUses[$first] . ($pos === false ? '' : \substr($name, $pos));
        }
        return \ltrim($ns . '\\' . $name, '\\');
    };
    $ref = static function (string $fqcn, string $kind) use (&$refs): void {
        \str_starts_with(\strtolower($fqcn), 'phpunit\\') and $refs[] = ['name' => $fqcn, 'kind' => $kind];
    };

    for ($i = 0; $i < $n; $i++) {
        $tok = $t[$i];
        $prev = $t[$i - 1] ?? null;
        $next = $t[$i + 1] ?? null;

        if ($tok->is(\T_NAMESPACE) && $next !== null && $next->is([\T_STRING, \T_NAME_QUALIFIED, '{'])) {
            $ns = $next->text === '{' ? '' : $next->text;
            $classUses = $funcUses = [];
            $next->text === '{' or $i++;
            continue;
        }

        if ($tok->is(\T_USE) && $stack === [] && $next?->text !== '(') {
            // Top-level import: `use A\B [as C], …;`, `use function …;`, `use A\{B, function c};`.
            $type = 'class';
            $j = $i + 1;
            if ($t[$j]->is([\T_FUNCTION, \T_CONST])) {
                $type = $t[$j]->is(\T_FUNCTION) ? 'function' : 'const';
                $j++;
            }
            $prefix = '';
            $itemType = $type;
            $cur = null;
            $alias = null;
            $afterAs = false;
            $commit = static function () use (&$cur, &$alias, &$afterAs, &$itemType, &$prefix, &$type, &$classUses, &$funcUses, &$imports, $ref): void {
                if ($cur !== null) {
                    $fq = $prefix . $cur;
                    $key = \strtolower($alias ?? \substr((string) \strrchr('\\' . $cur, '\\'), 1));
                    if ($itemType === 'class') {
                        $classUses[$key] = $fq;
                        \str_starts_with(\strtolower($fq), 'phpunit\\') and $imports[$key] = ['fqcn' => $fq, 'used' => false];
                    } elseif ($itemType === 'function') {
                        $funcUses[$key] = $fq;
                        $ref($fq, 'function_import');
                    }
                }
                $cur = $alias = null;
                $afterAs = false;
                $itemType = $type;
            };
            for (; $j < $n && $t[$j]->text !== ';'; $j++) {
                $x = $t[$j];
                if ($x->is(\T_AS)) {
                    $afterAs = true;
                } elseif ($isName($x)) {
                    if ($afterAs) {
                        $alias = $x->text;
                    } else {
                        $cur = \ltrim($x->text, '\\');
                    }
                } elseif ($x->text === '{') {
                    $prefix = \rtrim((string) $cur, '\\') . '\\';
                    $cur = null;
                } elseif ($x->is([\T_FUNCTION, \T_CONST])) {
                    $itemType = $x->is(\T_FUNCTION) ? 'function' : 'const';
                } elseif ($x->text === ',' || $x->text === '}') {
                    $commit();
                }
            }
            $commit();
            $i = $j;
            continue;
        }

        if ($tok->is(\T_ATTRIBUTE)) {
            $brackets = 1;
            $parens = 0;
            $head = true;
            for ($j = $i + 1; $j < $n && $brackets > 0; $j++) {
                $x = $t[$j];
                match ($x->text) {
                    '[' => $brackets++,
                    ']' => $brackets--,
                    '(' => $parens++,
                    ')' => $parens--,
                    default => null,
                };
                if ($x->text === ',' && $brackets === 1 && $parens === 0) {
                    $head = true;
                } elseif ($isName($x)) {
                    $fq = $resolve($x->text);
                    $head && $brackets === 1 && $parens === 0 and $attrs[] = \strtolower($fq);
                    $head = false;
                    $ref($fq, 'attribute');
                }
            }
            $i = $j - 1;
            continue;
        }

        if ($tok->is([\T_PUBLIC, \T_PROTECTED, \T_PRIVATE, \T_ABSTRACT, \T_STATIC, \T_FINAL, \T_READONLY])) {
            $mods[] = $tok->id;
            continue;
        }

        if ($tok->is([\T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM]) && !$prev?->is(\T_DOUBLE_COLON)) {
            $named = $next !== null && $next->is(\T_STRING);
            $parent = null;
            $section = '';
            for ($j = $i + 1; $j < $n && $t[$j]->text !== '{'; $j++) {
                $x = $t[$j];
                if ($x->is([\T_EXTENDS, \T_IMPLEMENTS])) {
                    $section = $x->is(\T_EXTENDS) ? 'extends' : 'implements';
                } elseif ($isName($x) && !($named && $j === $i + 1)) {
                    $fq = $resolve($x->text);
                    $isParent = $section === 'extends' && $tok->is(\T_CLASS);
                    $isParent and $parent = $fq;
                    $ref($fq, $isParent ? 'extends' : 'code');
                }
            }
            $stack[] = ['index' => $named ? \count($classes) : null, 'body' => $depth + 1];
            $named and $classes[] = [
                'kind' => \strtolower($tok->text),
                'name' => \ltrim($ns . '\\' . $next->text, '\\'),
                'parent' => $parent,
                'abstract' => \in_array(\T_ABSTRACT, $mods, true),
                'attrs' => $attrs,
                'methods' => [],
            ];
            $attrs = $mods = [];
            $i = $j - 1;
            continue;
        }

        if ($tok->is(\T_FUNCTION)) {
            $top = \end($stack);
            $j = $i + 1;
            $t[$j]->text === '&' and $j++;
            if ($top !== false && $top['index'] !== null && $top['body'] === $depth && isset($t[$j])) {
                $classes[$top['index']]['methods'][] = [
                    'name' => $t[$j]->text,
                    'public' => !\in_array(\T_PRIVATE, $mods, true) && !\in_array(\T_PROTECTED, $mods, true),
                    'abstract' => \in_array(\T_ABSTRACT, $mods, true),
                    'attrs' => $attrs,
                    'parentCalls' => [],
                ];
                $pendingMethod = [$top['index'], \count($classes[$top['index']]['methods']) - 1];
            }
            $attrs = $mods = [];
            $i = $j;
            continue;
        }

        if ($tok->is(['{', \T_CURLY_OPEN, \T_DOLLAR_OPEN_CURLY_BRACES])) {
            $depth++;
            if ($pendingMethod !== null) {
                $currentMethod = [...$pendingMethod, $depth];
                $pendingMethod = null;
            }
            $attrs = $mods = [];
            continue;
        }
        if ($tok->text === '}') {
            $depth--;
            $top = \end($stack);
            $top !== false && $top['body'] > $depth and \array_pop($stack);
            $currentMethod !== null && $currentMethod[2] > $depth and $currentMethod = null;
            $attrs = $mods = [];
            continue;
        }
        if ($tok->text === ';' && $pendingMethod !== null) {
            // An abstract or interface method: no body follows.
            $pendingMethod = null;
        }
        if ($tok->is([';', \T_VARIABLE, \T_CONST, \T_CASE])) {
            $attrs = $mods = [];
            continue;
        }

        if ($currentMethod !== null
            && \strtolower($tok->text) === 'parent'
            && $next?->is(\T_DOUBLE_COLON)
            && ($t[$i + 2] ?? null)?->is(\T_STRING)
        ) {
            [$c, $m] = $currentMethod;
            $classes[$c]['methods'][$m]['parentCalls'][] = $t[$i + 2]->text;
            $i += 2;
            continue;
        }

        if ($isName($tok)
            && !$prev?->is([\T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR, \T_DOUBLE_COLON, \T_CONST, \T_GOTO])
            && $next?->text !== ':'
        ) {
            $call = $next?->text === '(' && !$prev?->is(\T_NEW);
            $ref($resolve($tok->text, $call), $call ? 'function' : 'code');
        }
    }

    return ['classes' => $classes, 'imports' => $imports, 'refs' => $refs];
}

$hasTestoTest = static fn(array $attrs): bool => \in_array(TESTO_TEST_ATTR, $attrs, true);
$isTestName = static fn(string $name): bool => (bool) \preg_match('/^test[A-Z0-9_]/', $name);

/** Public, concrete `test*` methods of a class-like that Testo will not discover. */
$untaggedTests = static function (array $class) use ($hasTestoTest, $isTestName): array {
    if ($hasTestoTest($class['attrs'])) {
        return [];
    }
    $names = [];
    foreach ($class['methods'] as $method) {
        $method['public'] && !$method['abstract'] && $isTestName($method['name']) && !$hasTestoTest($method['attrs'])
            and $names[] = $method['name'];
    }
    return $names;
};

// --- Pass 1: outline every file, and collect what the scope itself declares ----------------------

/** @var list<array{abs:string, rel:string, code:string, outline:array}> $sources */
$sources = [];
$declaredClasses = [];
$declaredParents = [];
$declaredMethods = [];

foreach ($scopes as $scope) {
    $dir = $root . '/' . $scope;
    if (!\is_dir($dir)) {
        \fwrite(\STDERR, "warning: scope '{$scope}' not a directory, skipping\n");
        continue;
    }

    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
    );

    /** @var \SplFileInfo $file */
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $code = (string) \file_get_contents($file->getPathname());
        try {
            $outline = outline($code);
        } catch (\Throwable) {
            $outline = ['classes' => [], 'imports' => [], 'refs' => []];
        }
        foreach ($outline['classes'] as $class) {
            $declaredClasses[\strtolower($class['name'])] = true;
            $declaredParents[\strtolower($class['name'])] = $class['parent'];
            foreach ($class['methods'] as $method) {
                $declaredMethods[\strtolower($method['name'])] = true;
            }
        }

        $rel = \str_replace('\\', '/', \substr($file->getPathname(), \strlen($root) + 1));
        $sources[] = ['abs' => $file->getPathname(), 'rel' => $rel, 'code' => $code, 'outline' => $outline];
    }
}

// PHPUnit assertion names a project helper in the scope shadows are the helper, not residue.
$assertNames = \array_values(\array_filter(
    PHPUNIT_ASSERTS,
    static fn(string $name): bool => !isset($declaredMethods[\strtolower($name)]),
));
$leftoverAssertRe = '/(?:\$this->|\b(?:self|static)::)(?:' . \implode('|', $assertNames) . ')\s*\(/i';

/**
 * Whether a parent declared outside the scope is still a PHPUnit TestCase, and how many tests it
 * declares. Uses the project autoloader when available; otherwise guesses from the class name.
 *
 * @return array{testcase:bool, verified:bool, tests:?int, file:?string}
 */
$externalParent = (static function () use ($root, $autoload): \Closure {
    $cache = [];
    $loaded = null;
    return static function (string $fqcn) use (&$cache, &$loaded, $root, $autoload): array {
        if (isset($cache[$fqcn])) {
            return $cache[$fqcn];
        }
        if ($loaded === null) {
            $loaded = false;
            $composer = \json_decode((string) @\file_get_contents($root . '/composer.json'), true);
            $vendor = \is_array($composer) ? ($composer['config']['vendor-dir'] ?? 'vendor') : 'vendor';
            $file = $root . '/' . \trim((string) $vendor, '/\\') . '/autoload.php';
            if ($autoload && \is_file($file)) {
                \ob_start();
                try {
                    require_once $file;
                    $loaded = true;
                } catch (\Throwable) {
                } finally {
                    \ob_end_clean();
                }
            }
        }
        $guess = (bool) \preg_match('/TestCase$/i', $fqcn);
        $result = ['testcase' => $guess, 'verified' => false, 'tests' => null, 'file' => null];
        if ($loaded) {
            \ob_start();
            try {
                if (\class_exists($fqcn)) {
                    $class = new \ReflectionClass($fqcn);
                    $isTestCase = \strtolower($class->getName()) === PHPUNIT_TEST_CASE
                        || $class->isSubclassOf('PHPUnit\Framework\TestCase');
                    $tests = 0;
                    foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                        $method->isAbstract() || $method->getDeclaringClass()->getName() === 'PHPUnit\Framework\TestCase'
                            or ((\str_starts_with($method->getName(), 'test') || $method->getAttributes('PHPUnit\Framework\Attributes\Test') !== [])
                                and $tests++);
                    }
                    $path = \str_replace('\\', '/', (string) $class->getFileName());
                    $base = \str_replace('\\', '/', $root) . '/';
                    $result = [
                        'testcase' => $isTestCase,
                        'verified' => true,
                        'tests' => $tests,
                        'file' => $path === ''
                            ? null
                            : (\strncasecmp($path, $base, \strlen($base)) === 0 ? \substr($path, \strlen($base)) : $path),
                    ];
                }
            } catch (\Throwable) {
            } finally {
                \ob_end_clean();
            }
        }
        return $cache[$fqcn] = $result;
    };
})();

/**
 * The first ancestor outside the scope when it is still a PHPUnit TestCase other than
 * `PHPUnit\Framework\TestCase` itself, which Rector detaches. Walks the parents the scope declares.
 *
 * @return array{name:string, via:list<string>, info:array}|null
 */
$phpunitAncestor = static function (?string $parent) use ($declaredParents, $externalParent): ?array {
    $via = [];
    while ($parent !== null && \array_key_exists(\strtolower($parent), $declaredParents)) {
        if (\in_array($parent, $via, true)) {
            return null;
        }
        $via[] = $parent;
        $parent = $declaredParents[\strtolower($parent)];
    }
    if ($parent === null || \strtolower($parent) === PHPUNIT_TEST_CASE) {
        return null;
    }
    $info = $externalParent($parent);
    return $info['testcase'] ? ['name' => $parent, 'via' => $via, 'info' => $info] : null;
};

const TESTO_LIFECYCLE_ATTRS = [
    'testo\lifecycle\beforetest', 'testo\lifecycle\aftertest',
    'testo\lifecycle\beforeclass', 'testo\lifecycle\afterclass',
];
const PHPUNIT_HOOKS = ['setup', 'teardown', 'setupbeforeclass', 'teardownafterclass'];

/*
 * Each check: a regex (or a `detect` callback) + the to-do line the subagent must act on + a one-line hint.
 * A `detect` callback returns null for no match, or a list of details appended to the to-do line.
 * Ordered structural-first: the base-class / discovery work must happen before the file is
 * discoverable by Testo at all, so it leads the per-file `needs` list. `low` checks are cleanup that
 * alone does not warrant a subagent.
 */
$checks = [
    'extends_testcase' => [
        'detect' => static function (array $src): ?array {
            foreach ($src['outline']['classes'] as $class) {
                if (\strtolower((string) $class['parent']) === PHPUNIT_TEST_CASE) {
                    return [];
                }
            }
            return \preg_match('/\bextends\s+(?:\\\\?PHPUnit\\\\Framework\\\\)?TestCase\b/', $src['code']) ? [] : null;
        },
        'need' => 'Remove `extends TestCase`; mark the tests with Testo `#[Test]` (class-level, or per method).',
        'hint' => 'Testo requires no base class; discovery is attribute-based. Method names may keep their `test` prefix.',
    ],
    'external_testcase_parent' => [
        'detect' => static function (array $src) use ($phpunitAncestor): ?array {
            $found = [];
            foreach ($src['outline']['classes'] as $class) {
                $ancestor = $phpunitAncestor($class['parent']);
                if ($ancestor === null) {
                    continue;
                }
                $info = $ancestor['info'];
                $found[] = '`' . $ancestor['name'] . '`'
                    . ($ancestor['via'] === [] ? '' : ' via `' . \implode('` → `', $ancestor['via']) . '`')
                    . ($info['verified']
                        ? ' (' . $info['tests'] . ' inherited test method(s)' . ($info['file'] ? ', ' . $info['file'] : '') . ')'
                        : ' (unverified: no autoloader, guessed from the name)');
            }
            return $found === [] ? null : $found;
        },
        'need' => 'The parent class lies outside the scanned scope and is still a PHPUnit TestCase: its tests are invisible to Rector and Testo, and Rector marks the class extending it with `#[Skip]` naming that base. Copy the base into a local trait/abstract class under the test tree, port it, point this class at the copy, then remove the `#[Skip]`.',
        'hint' => 'See the mapping pitfall "Tests inherited from a vendor/ PHPUnit base"; carry `setUp`/`tearDown` over as `#[BeforeTest]`/`#[AfterTest]`.',
    ],
    'lifecycle_parent_call' => [
        'detect' => static function (array $src) use ($phpunitAncestor): ?array {
            $found = [];
            foreach ($src['outline']['classes'] as $class) {
                $parent = $class['parent'];
                $phpunitParent = $parent !== null
                    && (\strtolower($parent) === PHPUNIT_TEST_CASE || $phpunitAncestor($parent) !== null);
                if ($parent !== null && !$phpunitParent) {
                    continue;
                }
                foreach ($class['methods'] as $method) {
                    if (\array_intersect($method['attrs'], TESTO_LIFECYCLE_ATTRS) === []) {
                        continue;
                    }
                    $hooks = \array_filter(
                        $method['parentCalls'],
                        static fn(string $call): bool => \in_array(\strtolower($call), PHPUNIT_HOOKS, true),
                    );
                    foreach ($hooks as $hook) {
                        $found[] = "{$method['name']}() calls parent::{$hook}()"
                            . ($parent === null ? ' but the class has no parent' : " of the PHPUnit base `{$parent}`");
                    }
                }
            }
            return $found === [] ? null : \array_values(\array_unique($found));
        },
        'need' => 'A Testo lifecycle hook still calls a PHPUnit hook of its parent: without a parent it is a fatal error, and on a PHPUnit base Testo runs PHPUnit set-up logic outside PHPUnit. Detach the class from the PHPUnit base first, then drop the call or port what the parent hook did.',
        'hint' => 'A `parent::` hook call is fine only when the parent is a converted Testo base; see the mapping pitfall "Tests inherited from a vendor/ PHPUnit base".',
    ],
    'phpunit_test_attr' => [
        'detect' => static function (array $src) use ($untaggedTests): ?array {
            $found = [];
            foreach ($src['outline']['classes'] as $class) {
                if ($class['kind'] !== 'class') {
                    continue;
                }
                // A PHPUnit test class has a parent or a `*Test` name; a helper with a `testX()` method has neither.
                $testLike = $class['parent'] !== null
                    || \preg_match('/Test(?:Case)?$/', $class['name'])
                    || \array_filter($class['methods'], static fn(array $m): bool => \in_array(TESTO_TEST_ATTR, $m['attrs'], true)) !== [];
                \in_array(PHPUNIT_TEST_ATTR, $class['attrs'], true) and $found[] = 'PHPUnit #[Test] on class';
                foreach ($class['methods'] as $method) {
                    \in_array(PHPUNIT_TEST_ATTR, $method['attrs'], true) and $found[] = "PHPUnit #[Test] on {$method['name']}()";
                }
                foreach ($testLike ? $untaggedTests($class) : [] as $name) {
                    $found[] = "{$name}() has no Testo #[Test]";
                }
            }
            \preg_match('/@test\b/', $src['code']) and $found[] = '@test annotation';
            return $found === [] ? null : \array_values(\array_unique($found));
        },
        'need' => 'Convert PHPUnit test markers to Testo `#[Test]`: tests without it are not discovered.',
        'hint' => 'Class-level `#[Test]` is preferred when every public method is a test.',
    ],
    'untagged_trait_tests' => [
        'detect' => static function (array $src) use ($untaggedTests): ?array {
            $found = [];
            foreach ($src['outline']['classes'] as $class) {
                if ($class['kind'] === 'trait') {
                    foreach ($untaggedTests($class) as $name) {
                        $found[] = "{$name}()";
                    }
                }
            }
            return $found === [] ? null : $found;
        },
        'need' => 'Add Testo `#[Test]` to the trait\'s public `test*` methods: classes using the trait do not get them discovered otherwise.',
        'hint' => 'Mark the methods in the trait itself; the using classes may be outside this file.',
    ],
    'phpunit_functions' => [
        'detect' => static function (array $src): ?array {
            $found = [];
            foreach ($src['outline']['refs'] as $ref) {
                \in_array($ref['kind'], ['function', 'function_import'], true)
                    and $found[] = \substr((string) \strrchr($ref['name'], '\\'), 1) . '()';
            }
            return $found === [] ? null : \array_values(\array_unique($found));
        },
        'need' => 'Convert `PHPUnit\Framework\assert*()` function calls to `Assert::*` (mind the actual/expected order flip) and drop the `use function PHPUnit\Framework\…` imports.',
        'hint' => 'Those functions are not Testo assertions (the test turns Risky) and fatal once phpunit/phpunit is removed.',
    ],
    'phpunit_attribute' => [
        'detect' => static function (array $src): ?array {
            $found = [];
            foreach ($src['outline']['refs'] as $ref) {
                $ref['kind'] === 'attribute' && \strtolower($ref['name']) !== PHPUNIT_TEST_ATTR
                    and $found[] = '#[' . \substr((string) \strrchr('\\' . $ref['name'], '\\'), 1) . ']';
            }
            return $found === [] ? null : \array_values(\array_unique($found));
        },
        'need' => 'Replace the remaining PHPUnit attributes with their Testo equivalent, or drop them with a note when none exists.',
        'hint' => 'Testo ignores PHPUnit attributes (their behavior is silently lost) and they fatal on reflection once phpunit/phpunit is removed. See the mapping table.',
    ],
    'mocks' => [
        're'   => '/->(?:createMock|createStub|getMockBuilder|getMock|prophesize)\s*\(/',
        'need' => 'Replace PHPUnit mocks with a hand-rolled fake (preferred) or a kept mock library. Never mock final classes/enums.',
        'hint' => 'Testo ships no mocking. See the "Mocks" row of the map.',
    ],
    'assert_that' => [
        're'   => '/(?:\$this->|\b(?:self|static)::)assertThat\s*\(/',
        'need' => 'Rewrite `assertThat($v, $constraint)` as explicit `Assert::*` calls; there is no Testo constraint object.',
        'hint' => 'Decompose the constraint into concrete assertions.',
    ],
    'exception_regex' => [
        're'   => '/(?:\$this->|\b(?:self|static)::)expectExceptionMessageMatches\s*\(/',
        'need' => 'Convert the leftover `expectExceptionMessageMatches($re)` to `->withMessageMatchingRegex($re)` on the test\'s `Expect::exception(...)` chain, declared before the code that throws.',
        'hint' => 'Testo matches PCRE via `withMessageMatchingRegex()`. Rector folds it only when it directly follows `expectException()`; a leftover means other code sits in between or there is no `expectException()`.',
    ],
    'incomplete' => [
        're'   => '/(?:\$this->|\b(?:self|static)::)markTestIncomplete\s*\(/',
        'need' => 'Port `markTestIncomplete` to `throw new SkipTest(\'TODO: …\')` or leave the body empty (reported Risky).',
        'hint' => 'Testo has no Incomplete status.',
    ],
    'leftover_assert' => [
        're'   => $leftoverAssertRe,
        'need' => 'Convert remaining PHPUnit `$this->assert*` calls to `Assert::*` (mind the actual/expected order flip).',
        'hint' => 'Rector normally handles these — leftovers mean Rector was not run on this file or hit an edge case. Project helpers named `assert*` are not flagged.',
    ],
    'leftover_expect' => [
        're'   => '/(?:\$this->|\b(?:self|static)::)expect(?:Exception(?!MessageMatches\s*\()|NotToPerformAssertions)\w*\s*\(/',
        'need' => 'Convert remaining `$this->expect*` calls to `Expect::exception(...)` / `#[ExpectNoAssertions]`.',
        'hint' => 'Rector converts the bare forms; fluent message/code may remain.',
    ],
    'phpunit_reference' => [
        'detect' => static function (array $src): ?array {
            $found = [];
            foreach ($src['outline']['refs'] as $ref) {
                ($ref['kind'] === 'code' || ($ref['kind'] === 'extends' && \strtolower($ref['name']) !== PHPUNIT_TEST_CASE))
                    and $found[] = '`' . $ref['name'] . '`';
            }
            return $found === [] ? null : \array_values(\array_unique($found));
        },
        'need' => 'Remove the remaining code references to `PHPUnit\…` classes (constraints, exceptions, `MockObject` types, `Assert::` calls): rewrite them with Testo API.',
        'hint' => 'They fatal once phpunit/phpunit is removed.',
    ],
    'phpunit_import' => [
        'low'  => true,
        'detect' => static function (array $src): ?array {
            $found = [];
            foreach ($src['outline']['imports'] as $import) {
                $import['used'] or $found[] = '`' . $import['fqcn'] . '`';
            }
            return $found === [] ? null : $found;
        },
        'need' => 'Delete the unused `use PHPUnit\…` imports.',
        'hint' => 'Cleanup only; also fix any docblock that still names them.',
    ],
];

/** @var list<array{path:string, tests:int, needs:list<string>, hints:array<string,string>}> $files */
$files = [];
/** @var list<array{path:string, needs:list<string>}> $cleanup */
$cleanup = [];
$tally = \array_fill_keys(\array_keys($checks), 0);

// --- Pass 2: run the checks ----------------------------------------------------------------------

foreach ($sources as $src) {
    $needs = [];
    $hints = [];
    $blocking = false;
    foreach ($checks as $name => $check) {
        $details = isset($check['detect'])
            ? $check['detect']($src)
            : (\preg_match($check['re'], $src['code']) ? [] : null);
        if ($details === null) {
            continue;
        }
        $extra = \count($details) > 8 ? ', +' . (\count($details) - 8) . ' more' : '';
        $needs[] = $check['need'] . ($details === [] ? '' : ' Found: ' . \implode(', ', \array_slice($details, 0, 8)) . $extra . '.');
        $hints[$name] = $check['hint'];
        $tally[$name]++;
        empty($check['low']) and $blocking = true;
    }

    if ($needs === []) {
        continue;
    }
    if (!$blocking) {
        $cleanup[] = ['path' => $src['rel'], 'needs' => $needs];
        continue;
    }

    foreach ($src['outline']['classes'] as $class) {
        foreach ($class['methods'] as $method) {
            if (\str_starts_with($method['name'], 'test')
                && (\in_array(TESTO_TEST_ATTR, $method['attrs'], true) || \in_array(TESTO_TEST_ATTR, $class['attrs'], true))
            ) {
                $hints['test_prefix'] = 'Tests keep their `test*` names; discovery is by `#[Test]`. Renaming them is optional and cosmetic, not part of this port.';
                break 2;
            }
        }
    }

    $tests = \preg_match_all('/\bfunction\s+\w+\s*\([^)]*\)\s*:/', $src['code']);
    $files[] = ['path' => $src['rel'], 'tests' => $tests, 'needs' => $needs, 'hints' => $hints];
}

if ($files === [] && $cleanup === []) {
    \fwrite(\STDERR, "No files needing manual work found in: " . \implode(', ', $scopes) . ".\n");
    \fwrite(\STDERR, "If you already ran Rector, the mechanical part may be done — verify with `vendor/bin/testo`.\n");
    exit(2);
}

// Most-needs first: files with the longest to-do list are the heaviest ports.
\usort($files, static fn(array $a, array $b): int => \count($b['needs']) <=> \count($a['needs']));

// --- Batches ---
$batches = \array_chunk($files, $batch);
$index = [];
foreach ($batches as $i => $chunk) {
    $name = \sprintf('%03d.json', $i + 1);
    \file_put_contents(
        $batchDir . '/' . $name,
        (string) \json_encode($chunk, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
    );
    $index[] = ['file' => "migration-batches/{$name}", 'count' => \count($chunk)];
}

// --- Report ---
$label = [
    'extends_testcase'         => 'extends TestCase (structural)',
    'external_testcase_parent' => 'PHPUnit parent outside scope (structural)',
    'lifecycle_parent_call'    => 'lifecycle hook calling a PHPUnit parent hook',
    'phpunit_test_attr'        => 'undiscovered tests / PHPUnit test markers',
    'untagged_trait_tests'     => 'trait tests without #[Test]',
    'phpunit_functions'        => 'PHPUnit\Framework\assert*() functions',
    'phpunit_attribute'        => 'PHPUnit attributes',
    'mocks'                    => 'mocks',
    'assert_that'              => 'assertThat constraints',
    'exception_regex'          => 'leftover expectExceptionMessageMatches',
    'incomplete'               => 'markTestIncomplete',
    'leftover_assert'          => 'leftover $this->assert*',
    'leftover_expect'          => 'leftover $this->expect*',
    'phpunit_reference'        => 'PHPUnit class references in code',
    'phpunit_import'           => 'unused use PHPUnit\… (cleanup)',
];

$md = "# PHPUnit → Testo migration work-list\n\n";
$md .= "Scanned: " . \implode(', ', $scopes) . " — **" . \count($files) . " files need work**, in "
    . \count($batches) . " batch(es) of up to {$batch}.\n\n";

$md .= "## Residual constructs (files affected)\n\n";
$md .= "| Construct | Files |\n|---|--:|\n";
\arsort($tally);
foreach ($tally as $name => $count) {
    $count > 0 and $md .= "| " . $label[$name] . " | {$count} |\n";
}
$md .= "\n";

$md .= "## Files (most work first)\n\n";
$md .= "| File | Tests | To-do items |\n|---|--:|--:|\n";
foreach ($files as $f) {
    $md .= "| `{$f['path']}` | {$f['tests']} | " . \count($f['needs']) . " |\n";
}
$md .= "\n## Batches\n\n";
foreach ($index as $b) {
    $md .= "- `{$b['file']}` — {$b['count']} files\n";
}
$md .= "\nFeed each batch to the porting subagents (references/subagent-port-prompt.md). "
    . "Files are independent, so a batch may be run in PARALLEL — see the skill's Phase notes.\n";

if ($cleanup !== []) {
    $md .= "\n## Cleanup only (no subagent needed)\n\n";
    $md .= "These files need only low-severity cleanup; fix them directly, not via the batches.\n\n";
    foreach ($cleanup as $c) {
        $md .= "- `{$c['path']}`: " . \implode(' ', $c['needs']) . "\n";
    }
}

\file_put_contents($outAbs . '/migration-report.md', $md);

echo "Wrote {$out}/migration-report.md\n";
echo "Wrote " . \count($batches) . " batch(es) to {$out}/migration-batches/\n";
echo \count($files) . " files need work.\n";
$cleanup === [] or print(\count($cleanup) . " file(s) need cleanup only (listed in the report).\n");

exit(0);
