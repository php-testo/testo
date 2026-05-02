<?php

declare(strict_types=1);

namespace Testo\Inline;

use Internal\Container\Container;
use Testo\Common\PluginConfigurator;
use Testo\Inline\Internal\InlineFinder;
use Testo\Inline\Internal\InlineHandler;
use Testo\Pipeline\InterceptorCollector;

/**
 * Plugin that enables Inline Tests feature.
 *
 * @see TestInline
 * @link https://php-testo.github.io/docs/inline-tests
 * @api
 */
final readonly class InlineTestPlugin implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        $container->get(InterceptorCollector::class)->addInterceptor(new InlineFinder(new InlineHandler()));
    }
}
