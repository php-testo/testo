<?php

declare(strict_types=1);

namespace Testo\Application\Config\Plugin;

use Testo\Assert\AssertPlugin;
use Testo\Bench\BenchmarkPlugin;
use Testo\Common\PluginConfigurator;
use Testo\Facade\FacadePlugin;
use Testo\Inline\InlineTestPlugin;
use Testo\Lifecycle\LifecyclePlugin;
use Testo\Skip\SkipPlugin;
use Testo\Test\TestPlugin;

$_ = [];
\class_exists(AssertPlugin::class) and $_[] = new AssertPlugin();
\class_exists(BenchmarkPlugin::class) and $_[] = new BenchmarkPlugin();
\class_exists(FacadePlugin::class) and $_[] = new FacadePlugin();
\class_exists(InlineTestPlugin::class) and $_[] = new InlineTestPlugin();
\class_exists(LifecyclePlugin::class) and $_[] = new LifecyclePlugin();
\class_exists(SkipPlugin::class) and $_[] = new SkipPlugin();
\class_exists(TestPlugin::class) and $_[] = new TestPlugin();

\define([__NAMESPACE__ . '\DEFAULT_SUITE_PLUGINS'][0], $_);
unset($_);

/**
 * Plugin configuration facade.
 *
 * ```
 *  // Add to defaults
 *  SuitePlugins::with(new MyPlugin())
 *
 *  // Remove specific defaults
 *  SuitePlugins::without(LifecyclePlugin::class)
 *
 *  // Replace defaults entirely
 *  SuitePlugins::only(new MyPlugin())
 *
 *  // Chaining
 *  SuitePlugins::with(new A())->without(B::class)->with(new C())
 * ```
 *
 * @api
 */
final class SuitePlugins
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
     * @param class-string<\Testo\Common\PluginConfigurator> ...$pluginClasses
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
     * Returns the default suite-level plugins.
     */
    public static function defaults(): PluginCollection
    {
        return new PluginCollection(...DEFAULT_SUITE_PLUGINS);
    }
}
