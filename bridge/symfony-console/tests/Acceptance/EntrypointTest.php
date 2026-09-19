<?php

declare(strict_types=1);

namespace Tests\Bridge\SymfonyConsole\Acceptance;

use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Tests\Bridge\SymfonyConsole\Testo\Sandbox;

#[Test]
#[CoversNothing]
final class EntrypointTest
{
    private Sandbox $sandbox;

    public function loadsTheProjectAutoloaderWhenInstalledAsComposerDependency(): void
    {
        $entrypoint = $this->installEntrypointAsComposerDependency();

        [$exitCode, $stdout, $stderr] = self::runBinary($entrypoint, '--version', $this->sandbox->path('run'));

        self::assertRuns($exitCode, $stdout, $stderr, 'the installed entrypoint must load vendor/autoload.php three levels above its bin directory');
    }

    public function loadsTheBridgeLocalAutoloaderDuringDevelopment(): void
    {
        $entrypoint = $this->installEntrypointForLocalDevelopment();

        [$exitCode, $stdout, $stderr] = self::runBinary($entrypoint, '--version', $this->sandbox->path('run'));

        self::assertRuns($exitCode, $stdout, $stderr, 'the local-development entrypoint must load bridge/symfony-console/vendor/autoload.php');
    }

    public function loadsTheAutoloaderFromTheCurrentWorkingDirectoryAsFallback(): void
    {
        $entrypoint = $this->installEntrypointWithoutAdjacentAutoloaders();
        $workingDirectory = $this->sandbox->path('run');
        $this->linkComposerAutoloader($workingDirectory . '/vendor');

        [$exitCode, $stdout, $stderr] = self::runBinary($entrypoint, '--version', $workingDirectory);

        self::assertRuns($exitCode, $stdout, $stderr, 'the entrypoint must fall back to vendor/autoload.php in the current working directory');
    }

    #[BeforeTest]
    public function createSandbox(): void
    {
        $this->sandbox = Sandbox::create();
    }

    #[AfterTest]
    public function destroySandbox(): void
    {
        $this->sandbox->destroy();
    }

    private function installEntrypointAsComposerDependency(): string
    {
        $entrypoint = $this->installEntrypoint('vendor/testo/bridge-symfony-console/bin/testo');
        $vendor = $this->sandbox->path('vendor');

        $this->linkComposerAutoloader($vendor);
        $this->makeWorkingDirectory();

        return $entrypoint;
    }

    private function installEntrypointForLocalDevelopment(): string
    {
        $entrypoint = $this->installEntrypoint('bridge/symfony-console/bin/testo');

        $this->linkComposerAutoloader($this->sandbox->path('bridge/symfony-console/vendor'));
        $this->makeWorkingDirectory();

        return $entrypoint;
    }

    private function installEntrypointWithoutAdjacentAutoloaders(): string
    {
        $entrypoint = $this->installEntrypoint('isolated/bin/testo');
        $this->makeWorkingDirectory();

        return $entrypoint;
    }

    private function installEntrypoint(string $relativePath): string
    {
        $root = \dirname(__DIR__, 4);
        $entrypoint = $this->sandbox->path($relativePath);
        $bin = \dirname($entrypoint);

        \is_dir($bin) || \mkdir($bin, 0o755, true) or throw new \RuntimeException("Cannot create {$bin}");
        \copy($root . '/bridge/symfony-console/bin/testo', $entrypoint)
            or throw new \RuntimeException("Cannot install the testo entrypoint at {$relativePath}.");

        return $entrypoint;
    }

    private function linkComposerAutoloader(string $vendor): void
    {
        $root = \dirname(__DIR__, 4);

        \is_dir($vendor) || \mkdir($vendor, 0o755, true) or throw new \RuntimeException("Cannot create {$vendor}");
        \symlink($root . '/vendor/autoload.php', $vendor . '/autoload.php')
            or throw new \RuntimeException('Cannot link Composer autoloader.');
        \symlink($root . '/vendor/composer', $vendor . '/composer')
            or throw new \RuntimeException('Cannot link Composer metadata.');
    }

    private function makeWorkingDirectory(): void
    {
        \mkdir($this->sandbox->path('run')) or throw new \RuntimeException('Cannot create isolated working directory.');
    }

    private static function assertRuns(int $exitCode, string $stdout, string $stderr, string $expectation): void
    {
        Assert::same(
            $exitCode,
            0,
            $expectation . '; stdout: ' . $stdout . '; stderr: ' . $stderr,
        );
    }

    /**
     * @return array{int, string, string}
     */
    private static function runBinary(string $entrypoint, string $argument, string $workingDirectory): array
    {
        $process = \proc_open(
            [\PHP_BINARY, $entrypoint, $argument],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $workingDirectory,
        );
        \assert(\is_resource($process));
        \fclose($pipes[0]);

        $stdout = (string) \stream_get_contents($pipes[1]);
        $stderr = (string) \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);

        return [\proc_close($process), $stdout, $stderr];
    }
}
