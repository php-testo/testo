<?php

declare(strict_types=1);

namespace Tests\Bench\Unit;

use Testo\Bench\BenchmarkPlugin;
use Testo\Bench\Internal\Pipeline\BenchFinder;
use Testo\Codecov\Covers;
use Testo\Test;
use Testo\Testing\Helper\PluginTester;

/**
 * What {@see BenchmarkPlugin::configure()} wires, checked directly through {@see PluginTester}.
 */
#[Test]
#[Covers(BenchmarkPlugin::class)]
final class BenchmarkPluginTest
{
    public function registersOnlyTheBenchFinder(): void
    {
        PluginTester::for(new BenchmarkPlugin())
            ->addsInterceptor(BenchFinder::class)
            ->addsInterceptors(1)
            ->addsListeners(0);
    }
}
