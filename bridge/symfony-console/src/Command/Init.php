<?php

declare(strict_types=1);

namespace Testo\Bridge\Symfony\Console\Command;

use Internal\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Initializes Testo in the project:
 * 1. Resolves the source directory (src/ or user-provided)
 * 2. Creates tests/ and tests/Unit/ directories if missing
 * 3. Adds a "testo:unit" script to composer.json
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
    private const string STUB = __DIR__ . '/../../resources/stubs/testo.php';
    private const string DESTINATION = './testo.php';

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /**
         * Step src/
         */

        $srcPath = Path::create('src');
        if (!$srcPath->isDir()) {
            if (!$input->isInteractive()) {
                $io->warning('src/ directory not found. Skipping (non-interactive mode).');
                return Command::SUCCESS;
            }

            $srcPath = Path::create((string) $io->ask(
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
         * Step tests/ + Functional and Unit dir
         */

        $testsPath = Path::create('tests');
        if (!$testsPath->isDir()) {
            if ($input->isInteractive()) {
                $testsPath = Path::create((string) $io->ask(
                    question: 'tests/ directory not found. Path to tests directory (will be created if missing)',
                    default: 'tests',
                ));
            }

            if (!$testsPath->isDir()) {
                \mkdir((string) $testsPath, 0755, true);
                $io->success(\sprintf('Created %s/', $testsPath));
            }
        }

        $testsUnitPath = $testsPath->join('Unit');
        if (!$testsUnitPath->isDir()) {
            if ($input->isInteractive()) {
                $testsUnitPath = Path::create((string) $io->ask(
                    question: \sprintf('%s/Unit/ directory not found. Path to unit tests directory (will be created if missing)', $testsPath),
                    default: \sprintf('%s/Unit', $testsPath),
                ));
            }

            if (!$testsUnitPath->isDir()) {
                \mkdir((string) $testsUnitPath, 0755, true);
                $io->success(\sprintf('Created %s/', $testsUnitPath));
            }
        }

        /**
         * Step composer.json scripts
         */

        $composerJsonPath = Path::create('composer.json');
        if ($composerJsonPath->isFile()) {
            $composerJson = \json_decode(\file_get_contents((string) $composerJsonPath), true);
            $scriptKey = 'testo:unit';
            $scriptValue = 'vendor/bin/testo --suite=Unit';

            if (isset($composerJson['scripts'][$scriptKey])) {
                if (!$input->isInteractive()) {
                    $io->warning(\sprintf('"%s" script already exists in composer.json. Skipping (non-interactive mode).', $scriptKey));
                } elseif ($io->confirm(\sprintf('"%s" script already exists in composer.json. Update it?', $scriptKey), false)) {
                    $composerJson['scripts'][$scriptKey] = $scriptValue;
                    \file_put_contents(
                        (string) $composerJsonPath,
                        \json_encode($composerJson, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) . "\n",
                    );
                    $io->success(\sprintf('Updated "%s" script in composer.json', $scriptKey));
                }
            } else {
                $composerJson['scripts'][$scriptKey] = $scriptValue;
                \file_put_contents(
                    (string) $composerJsonPath,
                    \json_encode($composerJson, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) . "\n",
                );
                $io->success(\sprintf('Added "%s" script to composer.json', $scriptKey));
            }
        }

        /**
         * Step testo.php
         */

        if (\is_file(self::DESTINATION)) {
            if (!$input->isInteractive()) {
                $io->warning('testo.php already exists. Skipping (non-interactive mode).');
                return Command::SUCCESS;
            }

            if (!$io->confirm('testo.php already exists. Overwrite?', false)) {
                return Command::SUCCESS;
            }
        }

        $stubContent = \str_replace(
            ['__SRC_PATH__', '__TESTS_UNIT_PATH__'],
            [(string) $srcPath, (string) $testsUnitPath],
            \file_get_contents(self::STUB),
        );
        \file_put_contents(self::DESTINATION, $stubContent);
        $io->success('Created testo.php');


        /**
         * Step Final message
         *  - path/to/testo.php
         *  - example run tests.
         *  - Link to docs
         */

        $io->newLine();
        $io->text([
            \sprintf(' <info>Configuration:</info> %s', self::DESTINATION),
            ' <info>Run tests:</info>     composer testo:unit',
            ' <info>Documentation:</info> <href=https://php-testo.github.io/docs/intro/getting-started>https://php-testo.github.io/docs/intro/getting-started</>',
        ]);
        $io->newLine();

        return Command::SUCCESS;
    }
}
