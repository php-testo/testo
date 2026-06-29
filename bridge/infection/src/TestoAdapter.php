<?php

declare(strict_types=1);

namespace Testo\Bridge\Infection;

use Infection\AbstractTestFramework\TestFrameworkAdapter;
use Infection\StreamWrapper\IncludeInterceptor;

/**
 * Bridges Infection mutation testing with Testo.
 *
 * @internal
 */
final class TestoAdapter implements TestFrameworkAdapter
{
    /** @var non-empty-string Path to the Testo PHP entry script. */
    private readonly string $testFrameworkExecutable;

    public function __construct(
        string $testFrameworkExecutable,
        /** @var non-empty-string Absolute path to the project directory. */
        private readonly string $projectDir,
        /** @var non-empty-string Infection's tmp directory; safe to drop per-mutant bootstrap files in. */
        private readonly string $tmpDir,
        /**
         * @var non-empty-string Path where Infection expects the JUnit XML
         *      report. We pass it back to Testo via `--log-junit=<path>` and
         *      probe its existence in {@see hasJUnitReport()} to decide
         *      whether to use JUnit-driven test mapping or fall back to
         *      reflection-based resolution.
         */
        private readonly string $jUnitFilePath,
        /**
         * @var non-empty-string Directory where Infection expects the PHPUnit-style coverage XML
         *      (it reads `<dir>/index.xml`). We pass it back to Testo via `--coverage-xml=<dir>`,
         *      which activates the default (shadow) `CodecovPlugin` — so the coverage report is
         *      produced even when the user's `testo.php` declares no coverage plugin.
         */
        private readonly string $coverageXmlPath = '',
    ) {
        # On Windows, Infection's TestFrameworkFinder may hand us `bin/testo.bat`.
        # We can't `php testo.bat` — strip the `.bat` and run the sibling PHP script directly.
        if (\str_ends_with($testFrameworkExecutable, '.bat')) {
            $stripped = \substr($testFrameworkExecutable, 0, -4);
            \is_file($stripped) and $testFrameworkExecutable = $stripped;
        }

        $this->testFrameworkExecutable = $testFrameworkExecutable;
    }

    #[\Override]
    public function getName(): string
    {
        return 'Testo';
    }

    #[\Override]
    public function getVersion(): string
    {
        return 'unknown';
    }

    /**
     * Reports whether a JUnit XML file is on disk at the path Infection
     * expects. Probed dynamically rather than declared statically: if Testo
     * succeeded in writing the report (it does so when `--log-junit=<path>` is
     * passed and `JUnitPlugin` is configured), Infection switches to
     * JUnit-driven test→file mapping. If the file is missing for any reason
     * (Testo crashed before flush, plugin replaced, etc.), the bridge stays
     * on the reflection-based fallback in {@see getMutantCommandLine()}.
     */
    #[\Override]
    public function hasJUnitReport(): bool
    {
        return \is_file($this->jUnitFilePath);
    }

    /**
     * Decides whether a mutant's test run passed (mutant survived) from its captured stdout.
     *
     * Mutant runs are launched with `--json` (see {@see getMutantCommandLine()}), which collapses
     * stdout to a single compact verdict object — far cheaper than the per-test TeamCity stream,
     * whose volume could exhaust memory when Infection buffers a mutant's output. The JSON `status`
     * is the authoritative signal here: tests pass only when it is `"passed"`.
     *
     * Falls back to the TeamCity marker scan when the output is not the JSON verdict object (e.g. the
     * initial test run, which still uses `--teamcity`): tests pass when neither `testFailed` nor
     * `buildProblem` is present. Note that Infection consults this method only for an exit-code-0 run
     * (see {@see \Infection\Mutant\TestFrameworkMutantExecutionResultFactory}); a failing run exits
     * non-zero and is classified as killed before this is reached.
     */
    #[\Override]
    public function testsPass(string $output): bool
    {
        $decoded = \json_decode(\trim($output), true);
        if (\is_array($decoded) && isset($decoded['status'])) {
            return $decoded['status'] === 'passed';
        }

        return !\str_contains($output, '##teamcity[testFailed')
            && !\str_contains($output, '##teamcity[buildProblem');
    }

    #[\Override]
    public function getInitialTestsFailRecommendations(string $commandLine): string
    {
        return \sprintf(
            'The initial Testo run did not complete successfully. Try running it directly to see the failure:%s    %s',
            \PHP_EOL,
            $commandLine,
        );
    }

    #[\Override]
    public function getInitialTestRunCommandLine(string $extraOptions, array $phpExtraArgs, bool $skipCoverage): array
    {
        # Drop empty entries Infection may pass (e.g. when --initial-tests-php-options is unset).
        $phpExtraArgs = \array_values(\array_filter($phpExtraArgs, static fn(string $arg): bool => $arg !== ''));

        # Benches are timing-based and irrelevant to mutation testing; exclude them.
        $cmd = [\PHP_BINARY, ...$phpExtraArgs, $this->testFrameworkExecutable, 'run', '--type=!bench', '--teamcity'];

        if (!$skipCoverage) {
            $cmd[] = '--coverage';

            # Request the coverage XML at Infection's expected directory. Like JUnit below, the
            # default (shadow) CodecovPlugin in `ApplicationPlugins::defaults()` is inert until a
            # `--coverage-*` flag activates it, so this works without any coverage plugin in
            # testo.php; if the user declares one, the two are merged into a single collection.
            $this->coverageXmlPath === '' or $cmd[] = '--coverage-xml=' . $this->coverageXmlPath;
        }

        # Always request JUnit output at Infection's expected path. The
        # default JUnitPlugin in `ApplicationPlugins::defaults()` is inert
        # until activated by this flag; user-added instances ignore it.
        $cmd[] = '--log-junit=' . $this->jUnitFilePath;

        $extraOptions === '' or $cmd[] = $extraOptions;

        return $cmd;
    }

    #[\Override]
    public function getMutantCommandLine(
        array $coverageTests,
        string $mutatedFilePath,
        string $mutationHash,
        string $mutationOriginalFilePath,
        string $extraOptions,
    ): array {
        $bootstrap = $this->writeMutantBootstrap($mutationHash, $mutationOriginalFilePath, $mutatedFilePath);

        # `--json` collapses the run to one compact verdict object on stdout, instead of the
        # per-test TeamCity stream. Infection buffers each mutant's output in memory (via
        # symfony/process), so the smaller payload keeps peak memory bounded on segments whose
        # mutants would otherwise emit a large failing diff or thousands of TeamCity markers.
        # {@see testsPass()} reads the JSON `status` from this output.
        $cmd = [
            \PHP_BINARY,
            '-d',
            'auto_prepend_file=' . $bootstrap,
            $this->testFrameworkExecutable,
            'run',
            # Exclude benches; irrelevant to mutation testing.
            '--type=!bench',
            '--json',
            '--no-coverage',
        ];

        # Narrow Testo's discovery to the test files Infection knows cover this mutant.
        # This avoids tokenizing every test file in every suite just to filter most of them out.
        #
        # `TestLocation::filePath` is populated when Infection consumed a JUnit
        # report (see {@see hasJUnitReport()}). When it's not — either because
        # the report was missing or because that particular test wasn't found
        # in the XML (e.g. free-function tests) — we resolve the path from
        # the class/function name via reflection.
        $paths = [];
        $methods = [];
        foreach ($coverageTests as $test) {
            $method = self::stripDataSetSuffix($test->getMethod());
            $methods[$method] = true;

            $filePath = $test->getFilePath();
            if ($filePath !== null && $filePath !== '') {
                $paths[$filePath] = true;
                continue;
            }

            $path = self::resolveTestFilePath($method);
            $path === null or $paths[$path] = true;
        }
        foreach (\array_keys($paths) as $path) {
            $cmd[] = '--path';
            $cmd[] = $this->relativizeToProjectDir($path);
        }
        foreach (\array_keys($methods) as $method) {
            $cmd[] = '--filter';
            $cmd[] = $method;
        }

        $extraOptions === '' or $cmd[] = $extraOptions;

        return $cmd;
    }

    /**
     * Infection may pass `Class::method with data set #0` (PHPUnit-style); Testo's
     * `--filter` matches by method, so strip any trailing data-set suffix.
     */
    private static function stripDataSetSuffix(string $method): string
    {
        $pos = \strpos($method, ' with data set ');
        return $pos === false ? $method : \substr($method, 0, $pos);
    }

    /**
     * Resolves a test method id (`Class::method` or free function) to its source file
     * via reflection. Returns null if the class/function is missing or its file is unknown
     * (e.g. defined in C / eval'd code).
     */
    private static function resolveTestFilePath(string $method): ?string
    {
        $pos = \strpos($method, '::');
        $name = $pos === false ? $method : \substr($method, 0, $pos);

        try {
            $reflection = $pos === false
                ? (\function_exists($name) ? new \ReflectionFunction($name) : null)
                : (\class_exists($name) || \trait_exists($name) ? new \ReflectionClass($name) : null);
        } catch (\ReflectionException) {
            return null;
        }

        if ($reflection === null) {
            return null;
        }

        $file = $reflection->getFileName();
        return $file === false ? null : $file;
    }

    /**
     * Make a path relative to the project directory when possible, to keep the
     * mutant command line short. Windows caps `CreateProcess` arguments at ~32 KB,
     * so emitting absolute paths for every `--path` flag (one per covering test
     * file) can exceed the limit on large projects with `proc_open(): CreateProcess
     * failed, error code: 206`.
     *
     * Testo runs with cwd = project dir (inherited from Infection), so relative
     * paths resolve correctly.
     */
    private function relativizeToProjectDir(string $path): string
    {
        $base = \rtrim(\str_replace('\\', '/', $this->projectDir), '/');
        $normalized = \str_replace('\\', '/', $path);

        return \str_starts_with($normalized, $base . '/')
            ? \substr($normalized, \strlen($base) + 1)
            : $path;
    }

    /**
     * Writes a per-mutant auto_prepend_file with the original/mutant paths baked in.
     *
     * Avoids relying on env-variable inheritance, which Symfony Process does not reliably
     * propagate to child processes started without an explicit `env` argument.
     *
     * @return non-empty-string Absolute path to the generated bootstrap file.
     */
    private function writeMutantBootstrap(string $hash, string $original, string $mutant): string
    {
        \is_dir($this->tmpDir) or \mkdir($this->tmpDir, 0o755, true);

        $autoload = $this->projectDir . '/vendor/autoload.php';
        $interceptor = IncludeInterceptor::LOCATION;

        # Load the interceptor and enable it BEFORE requiring Composer's autoload, so that
        # `files`-autoloaded sources (e.g. `plugin/*/Repeat.php`, `Retry.php`) go through
        # the stream wrapper. Otherwise they'd be loaded with original content before the
        # interceptor is active, and mutations on those files would silently survive.
        $contents = \sprintf(
            <<<'PHP'
                <?php
                declare(strict_types=1);
                require_once %s;
                \Infection\StreamWrapper\IncludeInterceptor::intercept(%s, %s);
                \Infection\StreamWrapper\IncludeInterceptor::enable();
                require %s;
                PHP,
            \var_export($interceptor, true),
            \var_export($original, true),
            \var_export($mutant, true),
            \var_export($autoload, true),
        );

        $path = $this->tmpDir . '/testo-bootstrap-' . $hash . '.php';
        \file_put_contents($path, $contents);

        return $path;
    }
}
