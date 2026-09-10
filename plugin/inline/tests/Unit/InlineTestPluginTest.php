<?php

declare(strict_types=1);

namespace Tests\Inline\Unit;

use Testo\Codecov\Covers;
use Testo\Inline\Internal\InlineFinder;
use Testo\Inline\InlineTestPlugin;
use Testo\Test;
use Testo\Testing\Helper\PluginTester;

/**
 * What {@see InlineTestPlugin::configure()} wires, checked directly through {@see PluginTester}.
 */
#[Test]
#[Covers(InlineTestPlugin::class)]
final class InlineTestPluginTest
{
    public function registersOnlyTheInlineFinder(): void
    {
        PluginTester::for(new InlineTestPlugin())
            ->addsInterceptor(InlineFinder::class)
            ->addsInterceptors(1)
            ->addsListeners(0);
    }
}
