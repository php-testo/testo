<?php

declare(strict_types=1);

namespace Tests\Testo\Acceptance;

use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;

/**
 * Black-box acceptance test for the release-time constraint synchroniser
 * (.github/release-please/sync-deps.php).
 *
 * The script is procedural and resolves paths via getcwd(), so each case builds
 * an isolated fixture monorepo in a temp directory and runs the real script
 * there as a subprocess, then inspects the resulting composer.json files.
 *
 * No #[Covers]: the subject is a standalone CI script, not an autoloaded class.
 */
#[Test]
#[CoversNothing]
final class SyncDepsTest
{
    private const SCRIPT = '/.github/release-please/sync-deps.php';

    /**
     * Without a previous manifest the script can't tell what changed, so it falls
     * back to refreshing every package: siblings get a caret, testo/testo gets an
     * open upper bound, non-testo deps stay untouched.
     */
    public function refreshesEveryPackageWhenNoPreviousManifest(): void
    {
        $dir = $this->fixture(
            ['.' => '1.0.0', 'plugin/a' => '1.2.3', 'plugin/b' => '0.5.0'],
            [
                'composer.json' => $this->composer('testo/testo', [
                    'php' => '>=8.2',
                    'testo/a' => '0.1 - 1',
                    'testo/b' => '0.1 - 1',
                ]),
                'plugin/a/composer.json' => $this->composer('testo/a', [
                    'testo/b' => '0.1 - 1',
                    'testo/testo' => '*',
                ]),
                'plugin/b/composer.json' => $this->composer('testo/b'),
            ],
        );

        try {
            $this->run($dir);

            $root = $this->requireOf($dir, 'composer.json');
            Assert::same($root['testo/a'], '^1.2.3');
            Assert::same($root['testo/b'], '^0.5.0');
            Assert::same($root['php'], '>=8.2', 'non-testo dependency must stay untouched');

            $a = $this->requireOf($dir, 'plugin/a/composer.json');
            Assert::same($a['testo/b'], '^0.5.0', 'sibling: caret');
            Assert::same($a['testo/testo'], '1.0.0 - 1', 'core meta-package: open upper bound');
        } finally {
            $this->cleanup($dir);
        }
    }

    /**
     * With a previous manifest, only SUBPACKAGES whose version changed this cycle
     * are touched. For those, every testo/* constraint is refreshed (siblings
     * with a caret, testo/testo with an open upper bound). Dormant subpackages
     * keep all their constraints exactly as authored, so a release never churns a
     * subpackage it isn't publishing. The root composer.json is the exception: it
     * aggregates the whole monorepo for local dev, so it is always synced even
     * when the root package itself is dormant this cycle.
     */
    public function touchesReleasedSubpackagesAndAlwaysTheRoot(): void
    {
        $dir = $this->fixture(
            ['.' => '0.10.20', 'plugin/a' => '0.3.2', 'plugin/b' => '0.4.0'],
            [
                // root is dormant (version unchanged) but is always synced anyway
                'composer.json' => $this->composer('testo/testo', ['testo/a' => '0.1 - 1']),
                // plugin/a is released — everything in it gets refreshed
                'plugin/a/composer.json' => $this->composer('testo/a', [
                    'testo/testo' => '0.10.10 - 1',
                    'testo/b' => '0.1 - 1',
                ]),
                // plugin/b is dormant — left completely alone
                'plugin/b/composer.json' => $this->composer('testo/b', [
                    'testo/testo' => '0.10.10 - 1',
                    'testo/a' => '0.1 - 1',
                ]),
            ],
        );

        try {
            # Previous manifest: only plugin/a's version differs (0.3.1 -> 0.3.2).
            $this->run($dir, ['.' => '0.10.20', 'plugin/a' => '0.3.1', 'plugin/b' => '0.4.0']);

            Assert::same(
                $this->requireOf($dir, 'composer.json')['testo/a'],
                '^0.3.2',
                'root is always synced, even when the root package is dormant',
            );

            $a = $this->requireOf($dir, 'plugin/a/composer.json');
            Assert::same($a['testo/testo'], '0.10.20 - 1', 'released package: testo/testo refreshed');
            Assert::same($a['testo/b'], '^0.4.0', 'released package: sibling refreshed');

            $b = $this->requireOf($dir, 'plugin/b/composer.json');
            Assert::same($b['testo/testo'], '0.10.10 - 1', 'dormant package: testo/testo untouched');
            Assert::same($b['testo/a'], '0.1 - 1', 'dormant package: sibling untouched');
        } finally {
            $this->cleanup($dir);
        }
    }

    public function syncsRequireDevSection(): void
    {
        $dir = $this->fixture(
            ['.' => '1.0.0', 'bridge/infection' => '2.0.1'],
            [
                'composer.json' => $this->composer('testo/testo', [], [
                    'testo/bridge-infection' => '0.1 - 1',
                    'phpunit/phpunit' => '^10',
                ]),
                'bridge/infection/composer.json' => $this->composer('testo/bridge-infection'),
            ],
        );

        try {
            $this->run($dir);

            $dev = $this->read($dir, 'composer.json')['require-dev'];
            Assert::same($dev['testo/bridge-infection'], '^2.0.1');
            Assert::same($dev['phpunit/phpunit'], '^10', 'non-testo dev dependency must stay untouched');
        } finally {
            $this->cleanup($dir);
        }
    }

    /**
     * The root's path-repo dev-aliases are refreshed in lockstep with its
     * constraints so a local path package always advertises a version its own
     * constraint accepts (0.x tracks the minor, >=1.0.0 tracks the major). A
     * package absent from the manifest, and the `replace` / `suggest` sections,
     * are left exactly as authored.
     */
    public function syncsRootPathRepoVersionsButLeavesReplaceAndSuggestAlone(): void
    {
        $root = [
            'name' => 'testo/testo',
            'require' => ['testo/a' => '0.1 - 1', 'testo/b' => '0.1 - 1'],
            'suggest' => ['testo/a' => 'Some description — not a version constraint.'],
            'replace' => ['internal/foo' => '*'],
            'repositories' => [[
                'type' => 'path',
                'url' => 'plugin/*',
                'options' => ['versions' => [
                    'testo/a' => '0.1.x-dev',
                    'testo/b' => '0.1.x-dev',
                    'testo/unmanaged' => '0.1.x-dev',
                ]],
            ]],
        ];

        $dir = $this->fixture(
            ['.' => '1.0.0', 'plugin/a' => '1.2.3', 'plugin/b' => '0.5.7'],
            [
                'composer.json' => $this->encode($root),
                'plugin/a/composer.json' => $this->composer('testo/a'),
                'plugin/b/composer.json' => $this->composer('testo/b'),
            ],
        );

        try {
            $this->run($dir);

            $decoded = $this->read($dir, 'composer.json');
            Assert::same($decoded['require']['testo/a'], '^1.2.3', 'require is synced');
            Assert::same($decoded['suggest']['testo/a'], 'Some description — not a version constraint.', 'suggest left alone');
            Assert::same($decoded['replace']['internal/foo'], '*', 'replace left alone');

            $aliases = $decoded['repositories'][0]['options']['versions'];
            Assert::same($aliases['testo/a'], '1.x-dev', '>=1.0.0: alias tracks the major');
            Assert::same($aliases['testo/b'], '0.5.x-dev', '0.x: alias tracks the minor');
            Assert::same($aliases['testo/unmanaged'], '0.1.x-dev', 'package absent from the manifest is left alone');
        } finally {
            $this->cleanup($dir);
        }
    }

    public function leavesUnmanagedTestoPackagesUntouched(): void
    {
        $dir = $this->fixture(
            ['.' => '1.0.0'],
            ['composer.json' => $this->composer('testo/testo', ['testo/not-in-manifest' => '0.1 - 1'])],
        );

        try {
            $this->run($dir);

            $root = $this->requireOf($dir, 'composer.json');
            Assert::same($root['testo/not-in-manifest'], '0.1 - 1');
        } finally {
            $this->cleanup($dir);
        }
    }

    /**
     * The manifest carries a trailing sentinel key (`_`) so that a `merge=union`
     * of concurrent per-package bumps stays valid JSON. It names no package, so
     * the synchroniser must skip it rather than try to read `_/composer.json`.
     */
    public function ignoresTheSentinelManifestKey(): void
    {
        $dir = $this->fixture(
            ['.' => '1.0.0', 'plugin/a' => '1.2.3', '_' => ''],
            [
                'composer.json' => $this->composer('testo/testo', ['testo/a' => '0.1 - 1']),
                'plugin/a/composer.json' => $this->composer('testo/a'),
            ],
        );

        try {
            $this->run($dir);

            Assert::same($this->requireOf($dir, 'composer.json')['testo/a'], '^1.2.3');
        } finally {
            $this->cleanup($dir);
        }
    }

    public function isIdempotent(): void
    {
        $dir = $this->fixture(
            ['.' => '1.0.0', 'plugin/a' => '1.2.3'],
            [
                'composer.json' => $this->composer('testo/testo', ['testo/a' => '0.1 - 1']),
                'plugin/a/composer.json' => $this->composer('testo/a'),
            ],
        );

        try {
            $this->run($dir);
            $first = (string) \file_get_contents($dir . '/composer.json');

            $output = $this->run($dir);
            $second = (string) \file_get_contents($dir . '/composer.json');

            Assert::same($second, $first, 'a second run must not change anything');
            Assert::true(\str_contains($output, 'No intra-package constraints'));
        } finally {
            $this->cleanup($dir);
        }
    }

    /**
     * @param array<string, string> $manifest path => version
     * @param array<string, string> $composers relative path => json content
     */
    private function fixture(array $manifest, array $composers): string
    {
        $dir = \dirname(__DIR__, 2) . '/runtime/testo-syncdeps-' . \bin2hex(\random_bytes(6));
        \mkdir($dir . '/resources', 0o777, true);
        \file_put_contents($dir . '/resources/version.json', $this->encode($manifest));

        foreach ($composers as $relative => $content) {
            $path = $dir . '/' . $relative;
            $parent = \dirname($path);
            \is_dir($parent) or \mkdir($parent, 0o777, true);
            \file_put_contents($path, $content);
        }

        return $dir;
    }

    /**
     * @param array<string, string> $require
     * @param array<string, string> $requireDev
     */
    private function composer(string $name, array $require = [], array $requireDev = []): string
    {
        $data = ['name' => $name];
        $require === [] or $data['require'] = $require;
        $requireDev === [] or $data['require-dev'] = $requireDev;

        return $this->encode($data);
    }

    /**
     * @param array<string, string>|null $previousManifest base-branch manifest;
     *        when given, enables testo/testo syncing for changed packages
     */
    private function run(string $cwd, ?array $previousManifest = null): string
    {
        $script = \dirname(__DIR__, 3) . self::SCRIPT;
        $command = [\PHP_BINARY, $script];
        if ($previousManifest !== null) {
            $prev = $cwd . '/prev-version.json';
            \file_put_contents($prev, $this->encode($previousManifest));
            $command[] = $prev;
        }

        // Run via proc_open with plain pipes rather than Symfony Process: the latter
        // buffers output through sys_get_temp_dir() on Windows, which some agent
        // runners point at a directory that does not exist. The script's output is
        // small, so reading the pipes directly is safe.
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = \proc_open($command, $descriptors, $pipes, $cwd);
        \is_resource($proc) or throw new \RuntimeException('Cannot start ' . self::SCRIPT);

        \fclose($pipes[0]);
        $stdout = (string) \stream_get_contents($pipes[1]);
        $stderr = (string) \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $exitCode = \proc_close($proc);

        Assert::same($exitCode, 0, 'sync-deps.php exited with: ' . $stderr);

        return $stdout;
    }

    /** @return array<string, mixed> */
    private function read(string $dir, string $relative): array
    {
        return \json_decode((string) \file_get_contents($dir . '/' . $relative), true, flags: \JSON_THROW_ON_ERROR);
    }

    /** @return array<string, string> */
    private function requireOf(string $dir, string $relative): array
    {
        return $this->read($dir, $relative)['require'];
    }

    private function encode(mixed $data): string
    {
        return (string) \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    private function cleanup(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? \rmdir($item->getPathname()) : \unlink($item->getPathname());
        }
        \rmdir($dir);
    }
}
