<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Unit;

use Testo\Codecov\Covers;
use Testo\Lifecycle\Internal\LifecycleInterceptor;
use Testo\Lifecycle\LifecyclePlugin;
use Testo\Test;
use Testo\Testing\Helper\PluginTester;

/**
 * What {@see LifecyclePlugin::configure()} wires, checked directly through {@see PluginTester}.
 */
#[Test]
#[Covers(LifecyclePlugin::class)]
final class LifecyclePluginTest
{
    public function registersOnlyTheLifecycleInterceptor(): void
    {
        PluginTester::for(new LifecyclePlugin())
            ->addsInterceptor(LifecycleInterceptor::class)
            ->addsInterceptors(1)
            ->addsListeners(0);
    }
}
