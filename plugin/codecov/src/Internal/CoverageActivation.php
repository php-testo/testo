<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

use Internal\Container\Container;
use Internal\Path;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Config\CoverageMode;
use Testo\Codecov\Exception\CoverageDriverNotAvailable;
use Testo\Codecov\Internal\Driver\PcovDriver;
use Testo\Codecov\Internal\Driver\XdebugDriver;
use Testo\Codecov\Internal\Middleware\CoverageTestInterceptor;
use Testo\Codecov\Report\CoverageReport;
use Testo\Common\EventListenerCollector;
use Testo\Event\Framework\SessionStarting;
use Testo\Event\TestSuite\TestSuiteFinished;
use Testo\Pipeline\InterceptorCollector;

/**
 * Per-run coordinator that merges the contributions of every {@see \Testo\Codecov\CodecovPlugin}
 * instance into a single coverage collection.
 *
 * Several `CodecovPlugin` instances can be active at once — most commonly the inert shadow default
 * from {@see \Testo\Application\Config\Plugin\ApplicationPlugins::defaults()} (which carries the
 * `--coverage-clover` / `--coverage-cobertura` / `--coverage-xml` CLI reports) alongside a
 * user-declared plugin in `testo.php`. Running two independent collectors would double the
 * interception overhead and could corrupt the data, so this coordinator funnels them into one:
 *
 * - the **deepest** requested {@see CoverageLevel} wins (so every report gets the data it needs);
 * - the {@see CoverageMode} is the **strongest** (`Always` over `IfAvailable`);
 * - `testTypes` are **unioned** (an empty contribution means "all" and widens the set to all);
 * - every contributed report runs — user reports and CLI-flag reports side by side.
 *
 * Wiring (driver detection, the interceptor, the collector) is deferred to {@see SessionStarting},
 * which {@see \Testo\Application\Application::run()} dispatches *after* every application plugin has
 * configured. That ordering is what lets later plugins still raise the coverage level before the
 * driver is locked in, while staying well before any test pipeline runs.
 *
 * @internal
 * @psalm-internal Testo\Codecov
 */
final class CoverageActivation
{
    private bool $wired = false;
    private bool $cliReportsClaimed = false;

    /**
     * The coverage driver, created eagerly on the first contribution so XDebug's engine-level
     * filter (`xdebug_set_filter`, set inside {@see XdebugDriver::create()}) is installed before any
     * source file is autoloaded — the filter tags files at first include and cannot re-tag loaded
     * ones. Stays null when detection has run and found no usable driver.
     */
    private ?CoverageDriver $driver = null;

    private bool $driverResolved = false;
    private CoverageLevel $level = CoverageLevel::Line;
    private CoverageMode $mode = CoverageMode::IfAvailable;

    /**
     * Accumulated test-type allow-list as a set. An empty set means "all types" and, once it
     * becomes empty through a contribution, can never narrow again.
     *
     * @var array<non-empty-string, true>
     */
    private array $testTypes = [];

    private bool $allTestTypes = false;

    /** @var list<CoverageReport> */
    private array $reports = [];

    public function __construct(
        private readonly Container $container,
    ) {
        $container->get(EventListenerCollector::class)
            ->addListener(SessionStarting::class, $this->onSessionStarting(...));
    }

    /**
     * Returns the CLI-flag reports on the first call and an empty list afterwards, so that multiple
     * plugin instances don't each emit them.
     *
     * @return list<CoverageReport>
     */
    public function claimCliReports(CoverageInput $input): array
    {
        if ($this->cliReportsClaimed) {
            return [];
        }

        $this->cliReportsClaimed = true;
        return $input->resolveReports();
    }

    /**
     * Merges one plugin's coverage configuration into the shared collection.
     *
     * @param list<non-empty-string> $testTypes Empty means "all types".
     * @param list<CoverageReport> $reports
     */
    public function contribute(CoverageLevel $level, array $testTypes, array $reports, CoverageMode $mode): void
    {
        self::rank($level) > self::rank($this->level) and $this->level = $level;
        $mode === CoverageMode::Always and $this->mode = CoverageMode::Always;

        if ($testTypes === []) {
            $this->allTestTypes = true;
            $this->testTypes = [];
        } elseif (!$this->allTestTypes) {
            foreach ($testTypes as $type) {
                $this->testTypes[$type] = true;
            }
        }

        foreach ($reports as $report) {
            $this->reports[] = $report;
        }

        // Create the driver as soon as a plugin actually activates coverage. This runs during the
        // contributing plugin's `configure()`, i.e. before any suite/source file is loaded, so the
        // XDebug filter is installed in time. The coverage *level* is finalized later (in
        // `onSessionStarting()`) once every plugin has merged — `withLevel()` does not touch the
        // filter, so deferring it is safe. The path filter is the same for every plugin (it derives
        // from `ApplicationConfig::$src`), hence created once.
        if (!$this->driverResolved) {
            $this->driverResolved = true;
            $this->driver = self::detectAvailableDriver($this->container->get(ApplicationConfig::class)->src);
        }
    }

    /**
     * Single wiring point. Applies the merged level to the eagerly-created driver and registers the
     * interceptor and collector. Stays inert when nothing was contributed; honors {@see
     * CoverageMode::Always} by failing loudly when no driver is available, and skips silently
     * otherwise (soft activation).
     */
    public function onSessionStarting(): void
    {
        if ($this->wired || $this->reports === []) {
            return;
        }
        $this->wired = true;

        if ($this->driver === null) {
            $this->mode === CoverageMode::Always and throw new CoverageDriverNotAvailable();
            return;
        }

        $driver = $this->driver->withLevel($this->level);

        $this->container->get(InterceptorCollector::class)
            ->addInterceptor(new CoverageTestInterceptor($driver, \array_keys($this->testTypes)));

        $src = $this->container->get(ApplicationConfig::class)->src;
        $collector = new CoverageCollector($this->reports, self::resolveSourceRoot($src));
        $this->container->set($collector, destroy: true);

        $this->container->get(EventListenerCollector::class)
            ->addListener(TestSuiteFinished::class, static function (TestSuiteFinished $event) use ($collector): void {
                $collector->mergeSuiteResult($event->suiteResult);
            });
    }

    private static function rank(CoverageLevel $level): int
    {
        return match ($level) {
            CoverageLevel::Line => 0,
            CoverageLevel::Branch => 1,
            CoverageLevel::Path => 2,
        };
    }

    /**
     * Returns a usable driver if a coverage extension is present, otherwise null. Never throws — the
     * {@see CoverageMode::Always} "no driver" failure is decided in {@see onSessionStarting()} once
     * the merged mode is known. `Never` plugins return before contributing, so mode is not consulted
     * here.
     *
     * For XDebug this also installs the engine-level coverage filter (see {@see XdebugDriver::create()}).
     */
    private static function detectAvailableDriver(FinderConfig $src): ?CoverageDriver
    {
        return match (true) {
            \extension_loaded('pcov') => PcovDriver::create($src),
            self::isXdebugCoverageEnabled() => XdebugDriver::create($src),
            default => null,
        };
    }

    /**
     * Xdebug must be both loaded and running with `coverage` in its mode list;
     * otherwise `xdebug_start_code_coverage()` is a no-op and reports come back empty.
     */
    private static function isXdebugCoverageEnabled(): bool
    {
        if (!\extension_loaded('xdebug')) {
            return false;
        }

        $mode = \ini_get('xdebug.mode');
        if (!\is_string($mode) || $mode === '') {
            return false;
        }

        foreach (\explode(',', $mode) as $part) {
            if (\trim($part) === 'coverage') {
                return true;
            }
        }

        return false;
    }

    /**
     * Derives a project source root from the configured source includes.
     *
     * For the typical `src: ['src']` config, returns the parent of `src/` — the project root,
     * which is what reports want for relative-path computation. For multiple includes, returns
     * their common parent directory. Reports treat `null` as "fall back to {@see \getcwd()}".
     */
    private static function resolveSourceRoot(FinderConfig $src): ?string
    {
        if ($src->includes === []) {
            return null;
        }

        if (\count($src->includes) === 1) {
            $parent = (string) $src->includes[0]->parent();
            return $parent === '.' ? null : $parent;
        }

        // Common prefix at the directory-segment level.
        $segments = \array_map(
            static fn(Path $p): array => \explode('/', (string) $p),
            $src->includes,
        );
        $first = $segments[0];
        $commonLen = \count($first);
        foreach ($segments as $seg) {
            $i = 0;
            $max = \min($commonLen, \count($seg));
            while ($i < $max && $first[$i] === $seg[$i]) {
                $i++;
            }
            $commonLen = $i;
        }

        return $commonLen === 0 ? null : \implode('/', \array_slice($first, 0, $commonLen));
    }
}
