<?php

declare(strict_types=1);

namespace Tests\Assert\Unit;

use Testo\Assert\AssertPlugin;
use Testo\Assert\Internal\Middleware\AssertCollectorInterceptor;
use Testo\Assert\Internal\Middleware\ExpectationsInterceptor;
use Testo\Codecov\Covers;
use Testo\Test;
use Testo\Testing\Helper\PluginTester;

/**
 * What {@see AssertPlugin::configure()} wires, checked directly through {@see PluginTester}.
 */
#[Test]
#[Covers(AssertPlugin::class)]
final class AssertPluginTest
{
    public function registersBothAssertionInterceptors(): void
    {
        PluginTester::for(new AssertPlugin())
            ->addsInterceptor(AssertCollectorInterceptor::class)
            ->addsInterceptor(ExpectationsInterceptor::class)
            ->addsInterceptors(2)
            ->addsListeners(0);
    }
}
