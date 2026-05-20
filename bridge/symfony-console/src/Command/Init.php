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
    private const STUB = __DIR__ . '/../../resources/stubs/testo.php';
    private const DESTINATION = './testo.php';

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
         * Step tests/ + suites
         */

        $testsPath = Path::create('tests');
        if (!$testsPath->isDir()) {
            \mkdir((string) $testsPath, 0755, true);
            $io->success(\sprintf('Created %s/', $testsPath));
        }


        $knownSuites = ['Unit', 'Integration', 'Functional', 'Acceptance', 'Feature', 'E2E', 'Contract'];
        $detectedSuites = [];

        // Iterate over each known suite type.
        foreach ($knownSuites as $suite) {
            // Check if a directory named after the suite exists inside the tests path.
            if ($testsPath->join($suite)->isDir()) {
                $detectedSuites[] = $suite;
            }
        }

        // The unit must always be there, otherwise create it.
        if (!\in_array('Unit', $detectedSuites, true)) {
            \array_unshift($detectedSuites, 'Unit');

            $testsUnitPath = $testsPath->join('Unit');

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

            $scriptKeyAll = 'testo';
            $scriptValueAll = 'vendor/bin/testo';

            $scriptKeyTemplate = 'testo:%s';
            $scriptValueTemplate = 'vendor/bin/testo --suite=%s';

            if (!isset($composerJson['scripts'][$scriptKeyAll])) {
                $composerJson['scripts'][$scriptKeyAll] = $scriptValueAll;
                $io->success(\sprintf('Added "%s" script to composer.json', $scriptKeyAll));
            }

            $composerTestoKeyList = [$scriptKeyAll];
            foreach ($detectedSuites as $suite) {
                $scriptKey = \sprintf($scriptKeyTemplate, \strtolower($suite));
                $composerTestoKeyList[] = $scriptKey;
                if (!isset($composerJson['scripts'][$scriptKey])) {
                    $composerJson['scripts'][$scriptKey] = \sprintf($scriptValueTemplate, $suite);
                    $io->success(\sprintf('Added "%s" script to composer.json', $scriptKey));
                }
            }

            \file_put_contents(
                (string) $composerJsonPath,
                \json_encode($composerJson, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) . "\n",
            );
        }

        /**
         * Step testo.php
         */

        $shouldCreate = true;

        if (\is_file(self::DESTINATION)) {
            if (!$input->isInteractive()) {
                $io->warning('testo.php already exists. Skipping (non-interactive mode).');
                return Command::SUCCESS;
            }

            $shouldCreate = $io->confirm('testo.php already exists. Overwrite?', false);
        }

        if ($shouldCreate) {
            $suitesCode = '';
            foreach ($detectedSuites as $suite) {
                $suitesCode .= "        new SuiteConfig(\n            name: '$suite',\n            location: ['tests/$suite'],\n        ),\n";
            }

            $stubContent = \str_replace(
                ['__SRC_PATH__', '__SUITES__'],
                [(string) $srcPath, $suitesCode],
                \file_get_contents(self::STUB),
            );
            \file_put_contents(self::DESTINATION, $stubContent);
            $io->success('Created testo.php');
        }


        /**
         * Step Final message
         *  - path/to/testo.php
         *  - example run tests.
         *  - Link to docs
         */

        $io->newLine();
        $io->text(\array_merge(
            [
                \sprintf(' <info>Configuration:</info> %s', self::DESTINATION),
                ' <info>Documentation:</info> <href=https://php-testo.github.io/docs/intro/getting-started>https://php-testo.github.io/docs/intro/getting-started</>',
                ' <info>Run tests:</info>',
            ],
            \array_map(
                static fn(string $testoKey) => \sprintf('   <comment>$ composer %s</comment>', $testoKey),
                $composerTestoKeyList,
            ),
        ));
        $io->newLine();

        return Command::SUCCESS;
    }
}
