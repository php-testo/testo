<?php

declare(strict_types=1);

namespace Testo\Bridge\Vcr;

use Internal\Container\Container;
use Testo\Bridge\Vcr\Internal\VcrInterceptor;
use Testo\Common\PluginConfigurator;
use Testo\Pipeline\InterceptorCollector;
use VCR\VCR as PhpVcr;

/**
 * Plugin that drives PHP-VCR for tests annotated with {@see \Testo\Bridge\VCR}.
 *
 * Register it in your {@see \Testo\Application\Config\ApplicationConfig} `$plugins` list (or on a
 * single {@see \Testo\Application\Config\SuiteConfig}):
 *
 * ```php
 * // testo.php
 * return new ApplicationConfig(
 *     plugins: [new VcrPlugin(cassettePath: __DIR__ . '/tests/fixtures')],
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
    /**
     * @param string|null $cassettePath Directory PHP-VCR reads and writes cassettes in. `null` keeps
     *        php-vcr's default (`tests/fixtures`, relative to the working directory). The directory
     *        must exist. This is process-global in php-vcr, so the last configured suite wins.
     */
    public function __construct(
        private ?string $cassettePath = null,
    ) {}

    #[\Override]
    public function configure(Container $container): void
    {
        $this->cassettePath === null or PhpVcr::configure()->setCassettePath($this->cassettePath);
        $container->get(InterceptorCollector::class)->addInterceptor(new VcrInterceptor());
    }
}
