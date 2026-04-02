<?php

declare(strict_types=1);

namespace Testo\Codecov;

use Internal\Container\Container;
use Testo\Application\Config\ApplicationConfig;
use Testo\Codecov\Exception\CoverageDriverNotAvailable;
use Testo\Codecov\Internal\Driver\PcovDriver;
use Testo\Codecov\Internal\Driver\XdebugDriver;
use Testo\Codecov\Internal\Middleware\CoverageTestInterceptor;
use Testo\Common\PluginConfigurator;
use Testo\Pipeline\InterceptorCollector;

/**
 * Plugin that enables code coverage collection during test execution.
 *
 * Requires either the PCOV or XDebug extension to be installed.
 *
 * @api
 */
final readonly class CodecovPlugin implements PluginConfigurator
{
    #[\Override]
    public function configure(Container $container): void
    {
        $src = $container->get(ApplicationConfig::class)->src;
        $driver = self::detectDriver()->withFilter($src);

        $container->get(InterceptorCollector::class)
            ->addInterceptor(new CoverageTestInterceptor($driver, $src));
    }

    private static function detectDriver(): CoverageDriver
    {
        return match (true) {
            \extension_loaded('pcov') => new PcovDriver(),
            \extension_loaded('xdebug') => new XdebugDriver(),
            default => throw new CoverageDriverNotAvailable(),
        };
    }
}
