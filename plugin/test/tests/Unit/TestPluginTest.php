<?php

declare(strict_types=1);

namespace Tests\Test\Unit;

use Testo\Codecov\Covers;
use Testo\Test;
use Testo\Test\Internal\TestoAttributesLocatorInterceptor;
use Testo\Test\TestPlugin;
use Testo\Testing\Helper\PluginTester;

/**
 * What {@see TestPlugin::configure()} wires, checked directly through {@see PluginTester}.
 */
#[Test]
#[Covers(TestPlugin::class)]
final class TestPluginTest
{
    public function registersOnlyTheAttributeLocator(): void
    {
        PluginTester::for(new TestPlugin())
            ->addsInterceptor(TestoAttributesLocatorInterceptor::class)
            ->addsInterceptors(1)
            ->addsListeners(0);
    }
}
