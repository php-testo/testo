<?php

declare(strict_types=1);

namespace Testo\Bridge\Infection;

use Infection\AbstractTestFramework\TestFrameworkAdapter;

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
    ) {
        // On Windows, Infection's TestFrameworkFinder may hand us `bin/testo.bat`.
        // We can't `php testo.bat` — strip the `.bat` and run the sibling PHP script directly.
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

    #[\Override]
    public function hasJUnitReport(): bool
    {
        return false;
    }

    /**
     * Tests pass when the TeamCity stream contains no `testFailed` and no `buildProblem`.
     */
    #[\Override]
    public function testsPass(string $output): bool
    {
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
        // Drop empty entries Infection may pass (e.g. when --initial-tests-php-options is unset).
        $phpExtraArgs = \array_values(\array_filter($phpExtraArgs, static fn(string $arg): bool => $arg !== ''));

        $cmd = [\PHP_BINARY, ...$phpExtraArgs, $this->testFrameworkExecutable, 'run', '--teamcity'];

        $skipCoverage or $cmd[] = '--coverage';

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

        $cmd = [
            \PHP_BINARY,
            '-d',
            'auto_prepend_file=' . $bootstrap,
            $this->testFrameworkExecutable,
            'run',
            '--teamcity',
            '--no-coverage',
        ];

        // Narrow Testo's discovery to the test files Infection knows cover this mutant.
        // This avoids tokenizing every test file in every suite just to filter most of them out.
        $paths = [];
        foreach ($coverageTests as $test) {
            $filePath = $test->getFilePath();
            $filePath === null or $paths[$filePath] = true;
        }
        foreach (\array_keys($paths) as $path) {
            $cmd[] = '--path';
            $cmd[] = $path;
        }

        foreach ($coverageTests as $test) {
            $cmd[] = '--filter';
            $cmd[] = self::stripDataSetSuffix($test->getMethod());
        }

        $extraOptions === '' or $cmd[] = $extraOptions;

        return $cmd;
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

        $contents = \sprintf(
            <<<'PHP'
                <?php
                declare(strict_types=1);
                require %s;
                \Infection\StreamWrapper\IncludeInterceptor::intercept(%s, %s);
                \Infection\StreamWrapper\IncludeInterceptor::enable();
                PHP,
            \var_export($autoload, true),
            \var_export($original, true),
            \var_export($mutant, true),
        );

        $path = $this->tmpDir . '/testo-bootstrap-' . $hash . '.php';
        \file_put_contents($path, $contents);

        return $path;
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
}
