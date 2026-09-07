<?php

declare(strict_types=1);

namespace Tests\Bridge\SymfonyConsole\Stub;

use Internal\Container\Container;
use Testo\Common\PluginConfigurator;
use Testo\Output\ConsoleStreams;

/**
 * Points the reporters at a discard stream, so a nested in-process run's terminal rendering stays off
 * the outer run's real stdout.
 */
final class DiscardConsoleStreams implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        $sink = \fopen('php://temp', 'w+b');
        \assert($sink !== false);
        $container->set(new ConsoleStreams($sink, $sink), ConsoleStreams::class);
    }
}
