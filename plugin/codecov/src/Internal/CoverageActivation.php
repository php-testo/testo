<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

use Internal\Container\Container;
use Internal\Path;
use Psr\EventDispatcher\EventDispatcherInterface;
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
use Testo\Event\Report\ReportFileGenerating;
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
 * - the **deepest** requested {@see CoverageLevel} wins (so every report gets the data it needs),
 *   including the request of a plugin that contributes nothing else (see {@see requestLevel()});
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
     * Raises the merged analysis depth without activating collection. The deepest request wins; a
     * shallower one is ignored.
     *
     * Kept apart from {@see contribute()} so that a plugin which stays inert — one with no reports of
     * its own, e.g. `new CodecovPlugin(level: CoverageLevel::Branch)` in `testo.php` under a
     * `--coverage-clover` run — still has a say in the depth. The CLI-flag reports are claimed by
     * whichever instance configures first, which is normally the shadow default sitting on the `Line`
     * constructor default; without this the configured depth would be lost to that race.
     */
    public function requestLevel(CoverageLevel $level): void
    {
        self::rank($level) > self::rank($this->level) and $this->level = $level;
    }

    /**
     * Merges one plugin's coverage configuration into the shared collection.
     *
     * @param list<non-empty-string> $testTypes Empty means "all types".
     * @param list<CoverageReport> $reports
     */
    public function contribute(CoverageLevel $level, array $testTypes, array $reports, CoverageMode $mode): void
    {
        $this->requestLevel($level);
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
     * Enforces the merged {@see CoverageMode::Always} requirement: throws when coverage is mandatory
     * (e.g. a bare `--coverage`) but no driver is available.
     *
     * Kept separate from {@see contribute()} on purpose. It must be invoked from the plugin's
     * `configure()` — i.e. during `applyPlugins()`, *outside* the event dispatcher — because the
     * dispatcher swallows listener exceptions, so a throw deferred to {@see onSessionStarting()}
     * would be silently ignored and the run would pass with a misleading exit code. Idempotent: safe
     * to call after every contribution (a later plugin escalating the mode to `Always` is caught too).
     */
    public function verifyDriverRequirement(): void
    {
        $this->mode === CoverageMode::Always && $this->driver === null and throw new CoverageDriverNotAvailable();
    }

    /**
     * Single wiring point. Applies the merged level to the eagerly-created driver and registers the
     * interceptor and collector. Stays inert when there is nothing to write.
     *
     * The {@see CoverageMode::Always} "no driver" failure is raised earlier, in {@see
     * verifyDriverRequirement()} during plugin configuration, where the exception can actually abort
     * the run — so by the time this runs a non-null driver is guaranteed whenever the mode is
     * `Always`. An `IfAvailable` run with no driver skips silently here (soft activation).
     */
    public function onSessionStarting(): void
    {
        if ($this->wired || $this->reports === []) {
            return;
        }
        $this->wired = true;

        if ($this->driver === null) {
            return;
        }

        $driver = $this->driver->withLevel($this->level);

        $this->container->get(InterceptorCollector::class)
            ->addInterceptor(new CoverageTestInterceptor($driver, \array_keys($this->testTypes)));

        # Not in `configure()`: only here is a driver known, and an `IfAvailable` run without one would
        # have promised files it never writes.
        $dispatcher = $this->container->get(EventDispatcherInterface::class);
        foreach ($this->reports as $report) {
            $dispatcher->dispatch(new ReportFileGenerating($report->info()));
        }

        $src = $this->container->get(ApplicationConfig::class)->src;
        $collector = new CoverageCollector($this->reports, self::resolveSourceRoot($src), $dispatcher);
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
     * True when Xdebug is loaded and `coverage` is among its active modes; otherwise
     * `xdebug_start_code_coverage()` is a no-op and reports come back empty.
     *
     * `xdebug_info('mode')` (Xdebug ≥ 3.1) is the single source of truth: it returns the modes Xdebug
     * actually resolved across the `xdebug.mode` ini setting, the `-d xdebug.mode=...` CLI flag, and
     * the `XDEBUG_MODE` environment variable — so we don't replicate Xdebug's precedence by hand.
     * Reading `ini_get('xdebug.mode')` directly misses the env override (it is not reflected there),
     * which is exactly how `composer infect` and IDE coverage runners enable it on top of a different
     * ini/CLI mode.
     */
    private static function isXdebugCoverageEnabled(): bool
    {
        if (!\extension_loaded('xdebug')) {
            return false;
        }

        $modes = xdebug_info('mode');
        return \is_array($modes) && \in_array('coverage', $modes, true);
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
