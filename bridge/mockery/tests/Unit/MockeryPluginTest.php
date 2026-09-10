<?php

declare(strict_types=1);

namespace Tests\Bridge\Mockery\Unit;

use Testo\Bridge\Mockery\Internal\MockeryInterceptor;
use Testo\Bridge\Mockery\MockeryPlugin;
use Testo\Codecov\Covers;
use Testo\Test;
use Testo\Testing\Helper\PluginTester;

/**
 * What {@see MockeryPlugin::configure()} wires, checked directly through {@see PluginTester}.
 */
#[Test]
#[Covers(MockeryPlugin::class)]
final class MockeryPluginTest
{
    public function registersOnlyTheMockeryInterceptor(): void
    {
        PluginTester::for(new MockeryPlugin())
            ->addsInterceptor(MockeryInterceptor::class)
            ->addsInterceptors(1)
            ->addsListeners(0);
    }
}
