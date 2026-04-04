<?php

declare(strict_types=1);

namespace Testo\Codecov;

use Internal\Container\Container;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Config\CoverageMode;
use Testo\Codecov\Exception\CoverageDriverNotAvailable;
use Testo\Codecov\Internal\CoverageCollector;
use Testo\Codecov\Internal\CoverageDriver;
use Testo\Codecov\Internal\CoverageInput;
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
 * Default behavior is controlled by the `mode` constructor parameter.
 * CLI flags override the configured mode:
 * - `--coverage` → {@see CoverageMode::Required} (fail if no extension)
 * - `--no-coverage` → {@see CoverageMode::Disabled} (skip entirely)
 *
 * @api
 */
final readonly class CodecovPlugin implements PluginConfigurator
{
    /** @var list<CoverageReport> */
    private array $reports;

    /**
     * @param CoverageLevel $level Depth of coverage analysis.
     * @param CoverageMode $mode Default activation mode. Can be overridden by CLI flags
     *        (`--coverage` → Required, `--no-coverage` → Disabled).
     * @param list<CoverageReport> $reports Report generators to run after all tests complete.
     */
    public function __construct(
        private CoverageLevel $level = CoverageLevel::Line,
        private CoverageMode $mode = CoverageMode::Available,
        array $reports = [],
    ) {
        foreach ($reports as $report) {
            $report instanceof CoverageReport or throw new \InvalidArgumentException(\sprintf(
                'Codecov report must implement `%s`, got `%s`.',
                CoverageReport::class,
                \get_debug_type($report),
            ));
        }
        $this->reports = $reports;
    }

    #[\Override]
    public function configure(Container $container): void
    {
        // CLI flag overrides plugin config
        $mode = $container->get(CoverageInput::class)->resolveMode() ?? $this->mode;

        $src = $container->get(ApplicationConfig::class)->src;
        $driver = self::detectDriver($mode, $src);

        if ($driver === null) {
            return;
        }

        $driver = $driver->withLevel($this->level);

        $container->get(InterceptorCollector::class)
            ->addInterceptor(new CoverageTestInterceptor($driver));

        $aggregate = new CoverageCollector($this->reports);
        $container->set($aggregate, destroy: true);

        $container->get(EventListenerCollector::class)
            ->addListener(TestSuiteFinished::class, static function (TestSuiteFinished $event) use ($aggregate): void {
                $aggregate->mergeSuiteResult($event->suiteResult);
            });
    }

    private static function detectDriver(CoverageMode $mode, FinderConfig $src): ?CoverageDriver
    {
        return match (true) {
            $mode === CoverageMode::Disabled => null,
            \extension_loaded('pcov') => PcovDriver::create($src),
            \extension_loaded('xdebug') => XdebugDriver::create($src),
            $mode === CoverageMode::Required => throw new CoverageDriverNotAvailable(),
            default => null,
        };
    }
}
