<?php

declare(strict_types=1);

namespace Testo\Codecov;

use Internal\Container\Container;
use Internal\Path;
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
use Testo\Core\Value\TestType;
use Testo\Event\TestSuite\TestSuiteFinished;
use Testo\Pipeline\InterceptorCollector;

/**
 * Plugin that enables code coverage collection during test execution.
 *
 * Default behavior is controlled by the `collect` constructor parameter.
 * CLI flags override the configured mode:
 * - `--coverage` → {@see CoverageMode::Always} (fail if no extension)
 * - `--no-coverage` → {@see CoverageMode::Never} (skip entirely)
 *
 * @api
 */
final readonly class CodecovPlugin implements PluginConfigurator
{
    /** @var list<CoverageReport> */
    private array $reports;

    /** @var list<non-empty-string> */
    private array $testTypes;

    /**
     * @param CoverageLevel $level Depth of coverage analysis.
     * @param CoverageMode $collect Default activation mode. Can be overridden by CLI flags
     *        (`--coverage` → Always, `--no-coverage` → Never).
     * @param list<non-empty-string|\BackedEnum> $testTypes Test types to collect coverage for.
     *        Empty array means all types. Use {@see TestType} cases or custom string identifiers.
     * @param list<CoverageReport> $reports Report generators to run after all tests complete.
     */
    public function __construct(
        private CoverageLevel $level = CoverageLevel::Line,
        private CoverageMode $collect = CoverageMode::IfAvailable,
        array $testTypes = [TestType::Test, TestType::TestInline],
        array $reports = [],
    ) {
        $this->testTypes = \array_map(
            static fn(string|\BackedEnum $t): string => $t instanceof \BackedEnum ? $t->value : $t,
            $testTypes,
        );

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
        $mode = $container->get(CoverageInput::class)->resolveMode() ?? $this->collect;

        $src = $container->get(ApplicationConfig::class)->src;
        $driver = self::detectDriver($mode, $src);

        if ($driver === null) {
            return;
        }

        $driver = $driver->withLevel($this->level);

        $container->get(InterceptorCollector::class)
            ->addInterceptor(new CoverageTestInterceptor($driver, $this->testTypes));

        $aggregate = new CoverageCollector($this->reports, self::resolveSourceRoot($src));
        $container->set($aggregate, destroy: true);

        $container->get(EventListenerCollector::class)
            ->addListener(TestSuiteFinished::class, static function (TestSuiteFinished $event) use ($aggregate): void {
                $aggregate->mergeSuiteResult($event->suiteResult);
            });
    }

    private static function detectDriver(CoverageMode $mode, FinderConfig $src): ?CoverageDriver
    {
        return match (true) {
            $mode === CoverageMode::Never => null,
            \extension_loaded('pcov') => PcovDriver::create($src),
            \extension_loaded('xdebug') => XdebugDriver::create($src),
            $mode === CoverageMode::Always => throw new CoverageDriverNotAvailable(),
            default => null,
        };
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
