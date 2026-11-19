<?php

declare(strict_types=1);

namespace Tests\Bridge\SymfonyConsole\Stub;

use Internal\Container\Container;
use Testo\Common\PluginConfigurator;
use Testo\Output\ConsoleStreams;

/**
 * Points the reporters of a nested in-process run at a memory stream the test reads back.
 *
 * The run builds this plugin from its config file, so the test has no handle on the instance; the
 * stream is reachable through {@see self::output()} instead.
 */
final class CapturedConsoleStreams implements PluginConfigurator
{
    /** @var resource|null */
    private static $sink = null;

    /**
     * Everything the last configured run wrote to its console.
     */
    public static function output(): string
    {
        if (self::$sink === null) {
            return '';
        }

        \rewind(self::$sink);
        return (string) \stream_get_contents(self::$sink);
    }

    #[\Override]
    public function configure(Container $container): void
    {
        $sink = \fopen('php://memory', 'w+b');
        \assert($sink !== false);
        self::$sink = $sink;
        $container->set(new ConsoleStreams($sink, $sink), ConsoleStreams::class);
    }
}
