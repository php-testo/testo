<?php

declare(strict_types=1);

namespace Tests\Bridge\Double\Unit;

use Testo\Bridge\Double\DoublePlugin;
use Testo\Bridge\Double\Internal\DoubleInterceptor;
use Testo\Codecov\Covers;
use Testo\Test;
use Testo\Testing\Helper\PluginTester;

/**
 * What {@see DoublePlugin::configure()} wires, checked directly through {@see PluginTester} — the behaviour
 * suites can only exercise incidentally at suite bootstrap, where it is attributed to no test.
 */
#[Test]
#[Covers(DoublePlugin::class)]
final class DoublePluginTest
{
    public function registersOnlyTheDoubleInterceptor(): void
    {
        PluginTester::for(new DoublePlugin())
            ->addsInterceptor(DoubleInterceptor::class)
            ->addsInterceptors(1)
            ->addsListeners(0);
    }
}
