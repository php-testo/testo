<?php

declare(strict_types=1);

namespace Testo\Skip;

use Internal\Container\Container;
use Testo\Common\PluginConfigurator;
use Testo\Pipeline\InterceptorCollector;
use Testo\Skip;
use Testo\Skip\Internal\SkipLocatorInterceptor;

/**
 * Registers the locator that flags the `#[Skip]`-annotated tests ahead of the run.
 *
 * Part of the default suite plugins. Dropping it leaves the Skipped results intact — {@see Skip}
 * wires its own per-test interceptor — but nothing then learns of the skip before the test's own
 * pipeline starts, which is where a case-level decision would already be too late.
 *
 * @api
 */
final readonly class SkipPlugin implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        $container->get(InterceptorCollector::class)->addInterceptor(new SkipLocatorInterceptor());
    }
}
