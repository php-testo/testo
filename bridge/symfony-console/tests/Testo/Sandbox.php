<?php

declare(strict_types=1);

namespace Tests\Bridge\SymfonyConsole\Testo;

/**
 * Per-test scratch directory that doubles as the working directory.
 *
 * The Init command resolves `composer.json` and `src/` relative to the process
 * CWD, so acceptance assertions about those files require running the command
 * inside an isolated directory. {@see create()} mints a unique temp directory
 * and chdirs into it; {@see destroy()} restores the original CWD and wipes the
 * scratch tree. Both calls must be paired (typically via lifecycle hooks).
 */
final class Sandbox
{
    private function __construct(
        public readonly string $path,
        private readonly string $originalCwd,
    ) {}

    public static function create(): self
    {
        $originalCwd = \getcwd();
        $originalCwd === false and throw new \RuntimeException('Cannot determine current working directory.');

        $base = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'testo-init-' . \bin2hex(\random_bytes(6));
        \mkdir($base, 0o755, true) or throw new \RuntimeException("Cannot create sandbox at {$base}");

        $realBase = \realpath($base);
        $realBase === false and throw new \RuntimeException("Cannot resolve sandbox path {$base}");

        \chdir($realBase) or throw new \RuntimeException("Cannot chdir into sandbox {$realBase}");

        return new self($realBase, $originalCwd);
    }

    /**
     * Create a directory under the sandbox, including intermediate parents.
     */
    public function makeDir(string $relative): void
    {
        $target = $this->path . \DIRECTORY_SEPARATOR . \ltrim($relative, '/\\');
        \is_dir($target) or \mkdir($target, 0o755, true) or throw new \RuntimeException("Cannot create {$target}");
    }

    /**
     * Write a file under the sandbox, creating parent directories as needed.
     */
    public function writeFile(string $relative, string $contents): void
    {
        $target = $this->path . \DIRECTORY_SEPARATOR . \ltrim($relative, '/\\');
        $dir = \dirname($target);
        \is_dir($dir) or \mkdir($dir, 0o755, true);
        \file_put_contents($target, $contents) === false and throw new \RuntimeException("Cannot write {$target}");
    }

    /**
     * Absolute path to a file or directory inside the sandbox.
     */
    public function path(string $relative = ''): string
    {
        return $relative === ''
            ? $this->path
            : $this->path . \DIRECTORY_SEPARATOR . \ltrim($relative, '/\\');
    }

    public function destroy(): void
    {
        \chdir($this->originalCwd);
        self::removeRecursive($this->path);
    }

    private static function removeRecursive(string $path): void
    {
        if (!\file_exists($path) && !\is_link($path)) {
            return;
        }

        if (\is_file($path) || \is_link($path)) {
            @\unlink($path);
            return;
        }

        $entries = \scandir($path);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            self::removeRecursive($path . \DIRECTORY_SEPARATOR . $entry);
        }

        @\rmdir($path);
    }
}
