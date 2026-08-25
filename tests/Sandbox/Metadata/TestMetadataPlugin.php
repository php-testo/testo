<?php

declare(strict_types=1);

namespace Tests\Sandbox\Metadata;

use Internal\Container\Container;
use Testo\Common\PluginConfigurator;
use Testo\Pipeline\InterceptorCollector;

/**
 * Wires {@see TestMetadataInterceptor} into a suite so any test carrying {@see TestMetadata} reports
 * its values to TeamCity. A sandbox playground for exercising the `testMetadata` protocol across every
 * value type — numbers, text, links, images and artifacts, referenced both as local files and URLs.
 */
final class TestMetadataPlugin implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        $container->get(InterceptorCollector::class)->addInterceptor(new TestMetadataInterceptor());
    }
}
