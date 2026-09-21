<?php

declare(strict_types=1);

namespace Tests\Output\Stub\Channels;

use Internal\Container\Container;
use Testo\Common\PluginConfigurator;
use Testo\Core\Value\Verbosity;
use Testo\Output\ConsoleStreams;
use Testo\Output\Terminal\Renderer\ColorMode;

/**
 * Points a run's console at memory streams, at the verbosity where channel output streams and without
 * ANSI, so a feature test reads back exactly what a renderer wrote.
 *
 * Registered as an application plugin: the `run` command applies its renderer as a late plugin, after
 * the application plugins, so the renderer finds these bindings in place the way it does in production.
 */
final class CapturedConsolePlugin implements PluginConfigurator
{
    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        private $stdout,
        private $stderr,
    ) {}

    #[\Override]
    public function configure(Container $container): void
    {
        $container->set(new ConsoleStreams($this->stdout, $this->stderr));
        $container->set(Verbosity::Verbose, Verbosity::class);
        $container->set(ColorMode::Never, ColorMode::class);
    }
}
