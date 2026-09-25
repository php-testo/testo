<?php

declare(strict_types=1);

namespace Tests\Bench\Unit;

use Testo\Bench\BenchmarkPlugin;
use Testo\Bench\Internal\Pipeline\BenchFinder;
use Testo\Bench\Internal\Pipeline\BenchVerdictInterceptor;
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
    public function registersTheBenchFinderAndVerdict(): void
    {
        PluginTester::for(new BenchmarkPlugin())
            ->addsInterceptor(BenchFinder::class)
            ->addsInterceptor(BenchVerdictInterceptor::class)
            ->addsInterceptors(2)
            ->addsListeners(0);
    }
}
