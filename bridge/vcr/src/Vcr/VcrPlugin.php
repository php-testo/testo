<?php

declare(strict_types=1);

namespace Testo\Bridge\Vcr;

use Internal\Container\Container;
use Testo\Bridge\Vcr\Internal\VcrInterceptor;
use Testo\Common\PluginConfigurator;
use Testo\Pipeline\InterceptorCollector;

/**
 * Plugin that drives PHP-VCR for tests annotated with {@see \Testo\Bridge\VCR}.
 *
 * Register it in your {@see \Testo\Application\Config\ApplicationConfig} `$plugins` list (or on a
 * single {@see \Testo\Application\Config\SuiteConfig}):
 *
 * ```php
 * // testo.php
 * return new ApplicationConfig(
 *     plugins: [new VcrPlugin()],
 *     suites:  [new SuiteConfig(name: 'Feature', location: ['tests/Feature'])],
 * );
 * ```
 *
 * Once registered, every test carrying `#[VCR('cassette')]` runs with the named cassette inserted:
 * HTTP interactions are recorded on the first run and replayed on subsequent runs.
 *
 * @api
 */
final readonly class VcrPlugin implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        $container->get(InterceptorCollector::class)->addInterceptor(new VcrInterceptor());
    }
}
