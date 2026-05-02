<?php

declare(strict_types=1);

namespace Testo\Bench;

use Internal\Container\Container;
use Testo\Bench;
use Testo\Bench\Internal\Pipeline\BenchFinder;
use Testo\Common\PluginConfigurator;
use Testo\Pipeline\InterceptorCollector;

/**
 * Plugin that enables inline benchmarks feature.
 *
 * @see Bench
 * @api
 */
final readonly class BenchmarkPlugin implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        $container->get(InterceptorCollector::class)->addInterceptor(BenchFinder::class);
    }
}
