<?php

declare(strict_types=1);

namespace Testo\Application\Config\Plugin;

use Testo\Codecov\CodecovPlugin;
use Testo\Common\PluginConfigurator;
use Testo\Filter\FilterPlugin;
use Testo\Messenger\MessengerPlugin;
use Testo\Output\JUnit\JUnitPlugin;

$_ = [];
// \class_exists(TeamcityPlugin::class) and $_[] = new TeamcityPlugin();
// \class_exists(TerminalPlugin::class) and $_[] = new TerminalPlugin();
\class_exists(FilterPlugin::class) and $_[] = new FilterPlugin();
\class_exists(JUnitPlugin::class) and $_[] = new JUnitPlugin();
\class_exists(MessengerPlugin::class) and $_[] = new MessengerPlugin();
# Inert shadow: stays dormant unless a `--coverage-*` report flag activates it.
\class_exists(CodecovPlugin::class) and $_[] = new CodecovPlugin();

\define([__NAMESPACE__ . '\DEFAULT_APPLICATION_PLUGINS'][0], $_);
unset($_);

/**
 * Application-level plugin configuration facade.
 *
 * ```
 *  // Add to defaults
 *  ApplicationPlugins::with(new MyPlugin())
 *
 *  // Replace defaults entirely
 *  ApplicationPlugins::only(new MyPlugin())
 *
 *  // Chaining
 *  ApplicationPlugins::with(new A())->without(B::class)->with(new C())
 * ```
 *
 * @api
 */
final class ApplicationPlugins
{
    /**
     * Default plugins + the given plugins.
     */
    public static function with(PluginConfigurator ...$plugins): PluginCollection
    {
        return self::defaults()->with(...$plugins);
    }

    /**
     * Default plugins minus the specified classes.
     *
     * @param class-string<PluginConfigurator> ...$pluginClasses
     */
    public static function without(string ...$pluginClasses): PluginCollection
    {
        return self::defaults()->without(...$pluginClasses);
    }

    /**
     * No defaults — only the specified plugins.
     */
    public static function only(PluginConfigurator ...$plugins): PluginCollection
    {
        return new PluginCollection(...$plugins);
    }

    /**
     * Returns the default application-level plugins.
     */
    public static function defaults(): PluginCollection
    {
        return new PluginCollection(...DEFAULT_APPLICATION_PLUGINS);
    }
}
