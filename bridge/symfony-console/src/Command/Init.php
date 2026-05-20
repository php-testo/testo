<?php

declare(strict_types=1);

namespace Testo\Bridge\Symfony\Console\Command;

use Internal\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Initializes Testo in the project:
 * 1. Resolves the source directory (src/ or user-provided)
 * 2. Creates tests/ and tests/Unit/ directories if missing
 * 3. Adds testo scripts to composer.json
 * 4. Generates testo.php from a stub
 *
 * @internal
 */
#[AsCommand(
    name: 'init',
    description: 'Initialize Testo in your project',
)]
final class Init extends Command
{
    private const STUB = __DIR__ . '/../../resources/stubs/testo.php';
    private const CONFIG_FILENAME = 'testo.php';

    private const KNOWN_SUITES = ['Unit', 'Integration', 'Functional', 'Acceptance', 'Feature', 'E2E', 'Contract'];

    private const SCRIPT_ALL_KEY = 'test';
    private const SCRIPT_ALL_COMMAND = 'vendor/bin/testo';
    private const SCRIPT_SUITE_KEY_TEMPLATE = 'test:%s';
    private const SCRIPT_SUITE_COMMAND_TEMPLATE = 'vendor/bin/testo --suite=%s';

    #[\Override]
    protected function configure(): void
    {
        $this->addOption(
            name: 'path',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Base directory where tests/ and testo.php will be initialized',
            default: '.',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $basePath = Path::create((string) $input->getOption('path'));
            self::ensureDirectory($basePath, $io);

            $srcPath = self::discoverSourceDirectory($input, $io);

            $testsPath = $basePath->join('tests');
            self::ensureDirectory($testsPath, $io);

            $suites = self::discoverSuites($testsPath, $io);
            $composerKeys = self::updateComposerScripts($suites, $basePath, $io);

            $configPath = $basePath->join(self::CONFIG_FILENAME);

            # Non-interactive + existing config: bail out before touching the file.
            if ($configPath->isFile() && !$input->isInteractive()) {
                $io->warning(\sprintf('%s already exists. Skipping (non-interactive mode).', $configPath));
                return Command::SUCCESS;
            }

            self::writeConfig($configPath, $srcPath, $suites, $io);
            self::printSummary($configPath, $composerKeys, $io);
        } catch (\Throwable $exception) {
            $output->writeln('');
            $output->writeln(\sprintf('<fg=red>%s</>', $exception->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private static function ensureDirectory(Path $path, SymfonyStyle $io): void
    {
        if ($path->isDir()) {
            return;
        }

        \mkdir((string) $path, 0755, true);
        $io->success(\sprintf('Created %s/', $path));
    }

    private static function discoverSourceDirectory(InputInterface $input, SymfonyStyle $io): Path
    {
        $srcPath = Path::create('src');
        if ($srcPath->isDir()) {
            return $srcPath;
        }

        if (!$input->isInteractive()) {
            throw new \RuntimeException('src/ directory not found. Skipping (non-interactive mode).');
        }

        return Path::create((string) $io->ask(
            question: 'Path to source code',
            default: 'src',
            validator: static function (?string $value): string {
                $value ??= 'src';
                if (!Path::create($value)->isDir()) {
                    throw new \RuntimeException(\sprintf("Directory '%s' does not exist.", $value));
                }
                return $value;
            },
        ));
    }

    /**
     * Pick up every directory under tests/ matching a known suite name,
     * making sure Unit is always present (creating it if missing).
     *
     * @return list<string>
     */
    private static function discoverSuites(Path $testsPath, SymfonyStyle $io): array
    {
        $detected = [];
        foreach (self::KNOWN_SUITES as $suite) {
            if ($testsPath->join($suite)->isDir()) {
                $detected[] = $suite;
            }
        }

        if (!\in_array('Unit', $detected, true)) {
            \array_unshift($detected, 'Unit');
            self::ensureDirectory($testsPath->join('Unit'), $io);
        }

        return $detected;
    }

    /**
     * Merge `test` and `test:<suite>` scripts into the composer.json colocated with
     * the chosen base path (so monorepo sub-apps update their own composer.json, not
     * a parent one). Existing entries are preserved. Returns the full list of script
     * keys (for the final hint).
     *
     * @param list<string> $suites
     * @return list<string>
     */
    private static function updateComposerScripts(array $suites, Path $basePath, SymfonyStyle $io): array
    {
        $keys = [self::SCRIPT_ALL_KEY];

        $composerJsonPath = $basePath->join('composer.json');
        if (!$composerJsonPath->isFile()) {
            return $keys;
        }

        /** @var array{scripts?: array<string, string>}&array<string, mixed> $composer */
        $composer = \json_decode(
            \file_get_contents((string) $composerJsonPath),
            associative: true,
            flags: \JSON_THROW_ON_ERROR,
        );

        if (!isset($composer['scripts'][self::SCRIPT_ALL_KEY])) {
            $composer['scripts'][self::SCRIPT_ALL_KEY] = self::SCRIPT_ALL_COMMAND;
            $io->success(\sprintf('Added "%s" script to composer.json', self::SCRIPT_ALL_KEY));
        }

        foreach ($suites as $suite) {
            $key = \sprintf(self::SCRIPT_SUITE_KEY_TEMPLATE, \strtolower($suite));
            $keys[] = $key;

            if (!isset($composer['scripts'][$key])) {
                $composer['scripts'][$key] = \sprintf(self::SCRIPT_SUITE_COMMAND_TEMPLATE, $suite);
                $io->success(\sprintf('Added "%s" script to composer.json', $key));
            }
        }

        \file_put_contents(
            (string) $composerJsonPath,
            \json_encode($composer, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) . "\n",
        );

        return $keys;
    }

    /**
     * Render testo.php from the stub. Assumes the caller has already handled the
     * non-interactive "file exists" case; in interactive mode we still prompt.
     *
     * @param list<string> $suites
     */
    private static function writeConfig(Path $configPath, Path $srcPath, array $suites, SymfonyStyle $io): void
    {
        if ($configPath->isFile() && !$io->confirm(\sprintf('%s already exists. Overwrite?', $configPath), false)) {
            return;
        }

        $suitesCode = '';
        foreach ($suites as $suite) {
            $suitesCode .= "        new SuiteConfig(\n            name: '$suite',\n            location: ['tests/$suite'],\n        ),\n";
        }

        $stub = \str_replace(
            ['__SRC_PATH__', '__SUITES__'],
            # var_export emits PHP-literal quoting so paths containing apostrophes
            # or backslashes can't break out of the generated config.
            [\var_export((string) $srcPath, true), $suitesCode],
            \file_get_contents(self::STUB),
        );

        \file_put_contents((string) $configPath, $stub);
        $io->success(\sprintf('Created %s', $configPath));
    }

    /**
     * @param list<string> $composerKeys
     */
    private static function printSummary(Path $configPath, array $composerKeys, SymfonyStyle $io): void
    {
        $io->newLine();
        $io->text(\array_merge(
            [
                \sprintf(' <info>Configuration:</info> %s', $configPath),
                ' <info>Documentation:</info> <href=https://php-testo.github.io/docs/intro/getting-started>https://php-testo.github.io/docs/intro/getting-started</>',
                ' <info>Run tests:</info>',
            ],
            \array_map(
                static fn(string $key) => \sprintf('   <comment>$ composer %s</comment>', $key),
                $composerKeys,
            ),
        ));
        $io->newLine();
    }
}
