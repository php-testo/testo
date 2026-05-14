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
 * @internal
 */
#[AsCommand(
    name: 'init',
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

        //


        /**
         * Step composer.json scripts
         */

        //

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

        \file_put_contents(self::DESTINATION, \file_get_contents(self::STUB));
        $io->success('Created testo.php');


        /**
         * Final success
         *  - path/to/testo.php
         *  - example run tests.
         *  - Link to docs
         */

        //

        return Command::SUCCESS;
    }
}
