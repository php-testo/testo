<?php

declare(strict_types=1);

namespace Testo\Codecov;

use Internal\Container\Container;
use Testo\Application\Config\ApplicationConfig;
use Testo\Codecov\Exception\CoverageDriverNotAvailable;
use Testo\Codecov\Internal\CoverageAggregate;
use Testo\Codecov\Internal\Driver\PcovDriver;
use Testo\Codecov\Internal\Driver\XdebugDriver;
use Testo\Codecov\Internal\Middleware\CoverageTestInterceptor;
use Testo\Codecov\Report\CoverageReport;
use Testo\Common\EventListenerCollector;
use Testo\Common\PluginConfigurator;
use Testo\Event\TestSuite\TestSuiteFinished;
use Testo\Pipeline\InterceptorCollector;

/**
 * Plugin that enables code coverage collection during test execution.
 *
 * Behavior depends on {@see CoverageMode} in the container:
 * - {@see CoverageMode::Required} — throws if no extension available
 * - {@see CoverageMode::Available} — collects if extension present, skips silently if not (default)
 * - {@see CoverageMode::Disabled} — skips entirely
 *
 * @api
 */
final readonly class CodecovPlugin implements PluginConfigurator
{
    /** @var list<CoverageReport> */
    private array $reports;

    /**
     * @param CoverageReport ...$reports Report generators to run after all tests complete.
     */
    public function __construct(
        CoverageReport ...$reports,
    ) {
        $this->reports = $reports;
    }

    #[\Override]
    public function configure(Container $container): void
    {
        $mode = $container->has(CoverageMode::class)
            ? $container->get(CoverageMode::class)
            : CoverageMode::Available;
        $container->set($mode);

        $driver = self::detectDriver($mode);

        if ($driver === null) {
            return;
        }

        $src = $container->get(ApplicationConfig::class)->src;
        $driver = $driver->withFilter($src);

        $container->get(InterceptorCollector::class)
            ->addInterceptor(new CoverageTestInterceptor($driver));

        $aggregate = new CoverageAggregate($this->reports);
        $container->set($aggregate);

        $container->get(EventListenerCollector::class)
            ->addListener(TestSuiteFinished::class, static function (TestSuiteFinished $event) use ($aggregate): void {
                $aggregate->mergeSuiteResult($event->suiteResult);
            });
    }

    private static function detectDriver(CoverageMode $mode): ?CoverageDriver
    {
        return match (true) {
            $mode === CoverageMode::Disabled => null,
            \extension_loaded('pcov') => new PcovDriver(),
            \extension_loaded('xdebug') => new XdebugDriver(),
            $mode === CoverageMode::Required => throw new CoverageDriverNotAvailable(),
            default => null,
        };
    }
}
