<?php

declare(strict_types=1);

namespace Tests\Skip\Unit;

use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Skip\Internal\SkipLocatorInterceptor;
use Testo\Skip\SkipPlugin;
use Testo\Test;
use Testo\Testing\Helper\PluginTester;

/**
 * What {@see SkipPlugin::configure()} wires, checked directly through {@see PluginTester}, and
 * where the plugin sits.
 */
#[Test]
#[Covers(SkipPlugin::class)]
final class SkipPluginTest
{
    public function registersOnlyTheLocatorInterceptor(): void
    {
        PluginTester::for(new SkipPlugin())
            ->addsInterceptor(SkipLocatorInterceptor::class)
            ->addsInterceptors(1)
            ->addsListeners(0);
    }

    /**
     * The lifecycle hooks learn about a skip from the flag the plugin sets, so the plugin has to be
     * on by default, like the lifecycle plugin itself.
     */
    public function isAmongTheDefaultSuitePlugins(): void
    {
        $classes = \array_map(
            static fn(object $plugin): string => $plugin::class,
            \iterator_to_array(SuitePlugins::defaults(), false),
        );

        Assert::contains($classes, SkipPlugin::class);
    }
}
