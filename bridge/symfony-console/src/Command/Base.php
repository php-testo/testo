<?php

declare(strict_types=1);

namespace Testo\Bridge\Symfony\Console\Command;

use Internal\Container\Container;
use Internal\Path;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Testo\Application\Application;
use Testo\Core\Value\Verbosity;
use Testo\Output\Terminal\Renderer\ColorMode;
use Yiisoft\Injector\Injector;

/**
 * Base abstract class for all commands.
 *
 * Provides common functionality for command initialization, container setup,
 * and configuration handling.
 *
 * ```
 *  // Extend to create a custom command
 *  final class CustomCommand extends Base
 *  {
 *      protected function __invoke(
 *          InputInterface $input,
 *          OutputInterface $output,
 *          ClassName $anyParam): int
 *      {
 *          // Return a Command code
 *          return Command::SUCCESS;
 *      }
 *  }
 * ```
 *
 * @internal
 */
abstract class Base extends Command
{
    /** @var \Internal\Container\Container IoC container with services */
    protected Container $container;

    protected Application $application;

    /**
     * Configures command options.
     *
     * Adds option for specifying configuration file location.
     */
    #[\Override]
    public function configure(): void
    {
        parent::configure();
        $this->addOption('config', null, InputOption::VALUE_OPTIONAL, 'Path to the configuration file');
    }

    /**
     * Initializes the command execution environment.
     *
     * Sets up logger, container, and registers input/output in the container.
     *
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     *
     * @return int Command success code
     */
    #[\Override]
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $this->application = Application::createFromInput(
            configFile: $this->getConfigFile($input),
            inputOptions: $input->getOptions(),
            inputArguments: $input->getArguments(),
            environment: \getenv(),
        );
        $this->container = $this->application->getContainer();

        $this->container->set($input, InputInterface::class);
        $this->container->set($output, OutputInterface::class);
        $this->container->set(new SymfonyStyle($input, $output), StyleInterface::class);
        $this->container->set(self::resolveVerbosity($output), Verbosity::class);
        $this->container->set(self::resolveColorMode($input), ColorMode::class);

        return $this->container->get(Injector::class)->invoke($this) ?? Command::SUCCESS;
    }

    /**
     * Resolves configuration file path from input or default location.
     *
     * @param InputInterface $input Command input
     *
     * @return Path|null Path to the configuration file
     */
    protected function getConfigFile(InputInterface $input): ?Path
    {
        /** @var string|null $config */
        $config = $input->getOption('config');
        $isConfigured = $config !== null;
        // $config ??= './testo.xml';
        $config ??= './testo.php';

        if (\is_file($config)) {
            return Path::create($config);
        }

        $isConfigured and throw new \InvalidArgumentException(
            'Configuration file not found: ' . $config,
        );

        return null;
    }

    /**
     * Maps Symfony's console verbosity onto the framework's {@see Verbosity} level.
     */
    private static function resolveVerbosity(OutputInterface $output): Verbosity
    {
        return match ($output->getVerbosity()) {
            OutputInterface::VERBOSITY_QUIET => Verbosity::Quiet,
            OutputInterface::VERBOSITY_VERBOSE => Verbosity::Verbose,
            OutputInterface::VERBOSITY_VERY_VERBOSE => Verbosity::VeryVerbose,
            OutputInterface::VERBOSITY_DEBUG => Verbosity::Debug,
            default => Verbosity::Normal,
        };
    }

    /**
     * Colors stay on unless they are turned off explicitly — by `--no-ansi` or by the `NO_COLOR`
     * convention (<https://no-color.org/>). A redirected stdout keeps them: piping into a file or a
     * pager is not by itself a request for plain text, and a caller who wants plain text has a flag
     * to say so.
     *
     * This deliberately does not go through {@see OutputInterface::isDecorated()}, which folds the
     * explicit request together with TTY detection and so cannot tell "the user asked for no color"
     * apart from "stdout is not a terminal".
     */
    private static function resolveColorMode(InputInterface $input): ColorMode
    {
        $disabled = $input->hasParameterOption('--no-ansi', true)
            || ($_SERVER['NO_COLOR'] ?? $_ENV['NO_COLOR'] ?? '') !== '';

        return $disabled ? ColorMode::Never : ColorMode::Always;
    }
}
