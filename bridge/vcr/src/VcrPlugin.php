<?php

declare(strict_types=1);

namespace Testo\Bridge\VCR;

use Internal\Container\Container;
use Testo\Common\PluginConfigurator;
use VCR\VCR as PhpVcr;

/**
 * Optional plugin for the PHP-VCR bridge.
 *
 * The {@see \Testo\Bridge\VCR} attribute is self-wiring (it is `Interceptable`), so `#[VCR]` tests work
 * without any plugin. Register this plugin only to point php-vcr at a non-default cassette directory:
 *
 * ```php
 * // testo.php
 * return new ApplicationConfig(
 *     plugins: [new VcrPlugin(cassettePath: __DIR__ . '/tests/fixtures')],
 *     suites:  [new SuiteConfig(name: 'Feature', location: ['tests/Feature'])],
 * );
 * ```
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
    }
}
