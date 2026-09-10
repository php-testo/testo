<?php

declare(strict_types=1);

namespace Tests\Core\Testing\Unit;

use Testo\Codecov\Covers;
use Testo\Test;
use Testo\Testing\Helper\PluginTester;
use Testo\Testing\InjectPlugin;
use Testo\Testing\Internal\InjectInterceptor;

/**
 * What {@see InjectPlugin::configure()} wires, checked directly through {@see PluginTester}.
 */
#[Test]
#[Covers(InjectPlugin::class)]
final class InjectPluginTest
{
    public function registersOnlyTheInjectInterceptor(): void
    {
        PluginTester::for(new InjectPlugin())
            ->addsInterceptor(InjectInterceptor::class)
            ->addsInterceptors(1)
            ->addsListeners(0);
    }
}
