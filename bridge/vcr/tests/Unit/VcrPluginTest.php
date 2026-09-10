<?php

declare(strict_types=1);

namespace Tests\Bridge\VCR\Unit;

use Testo\Bridge\VCR\VcrPlugin;
use Testo\Codecov\Covers;
use Testo\Test;
use Testo\Testing\Helper\PluginTester;

/**
 * The {@see \Testo\Bridge\VCR} attribute is self-wiring, so a plugin with no cassette path has nothing
 * to configure — {@see PluginTester} confirms it stays inert, registering no interceptor or listener.
 */
#[Test]
#[Covers(VcrPlugin::class)]
final class VcrPluginTest
{
    public function withoutACassettePathRegistersNothing(): void
    {
        PluginTester::for(new VcrPlugin())
            ->addsInterceptors(0)
            ->addsListeners(0);
    }
}
