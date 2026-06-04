<?php

declare(strict_types=1);

namespace Testo\Codecov;

use Internal\Container\Container;
use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Config\CoverageMode;
use Testo\Codecov\Internal\CoverageActivation;
use Testo\Codecov\Internal\CoverageInput;
use Testo\Codecov\Report\CoverageReport;
use Testo\Common\PluginConfigurator;
use Testo\Core\Value\TestType;

/**
 * Plugin that enables code coverage collection during test execution.
 *
 * Default behavior is controlled by the `collect` constructor parameter.
 * CLI flags override the configured mode:
 * - `--coverage` → {@see CoverageMode::Always} (fail if no extension)
 * - `--no-coverage` → {@see CoverageMode::Never} (skip entirely)
 *
 * # Reports and CLI flags
 *
 * Reports are normally declared via the `reports` constructor argument. In addition, three CLI
 * flags let external tools (the IDE plugin, Infection) pin report destinations regardless of
 * `testo.php`:
 * - `--coverage-clover=<file>` → a {@see Report\CloverReport}
 * - `--coverage-cobertura=<file>` → a {@see Report\CoberturaReport}
 * - `--coverage-xml=<dir>` → a {@see Report\PhpUnitXmlReport}
 *
 * Passing any of these implies coverage collection at {@see CoverageMode::IfAvailable} (collect and
 * write if a driver is present, skip silently otherwise); `--no-coverage` still wins.
 *
 * # How activation works
 *
 * A `CodecovPlugin` is part of {@see \Testo\Application\Config\Plugin\ApplicationPlugins::defaults()},
 * so every project gets one **inert** shadow copy for free. Inert means: with no reports to write
 * (no `reports` argument and no CLI report flag) the plugin does nothing. The shadow exists so the
 * CLI report flags activate coverage without any change to `testo.php`.
 *
 * Multiple instances are **merged**, not run side by side: the shadow default and a user-declared
 * plugin both feed a single {@see CoverageActivation} coordinator that collects coverage once. The
 * merged collection uses the deepest requested {@see CoverageLevel}, the strongest mode, the union
 * of `testTypes`, and runs every contributed report (user reports + CLI-flag reports). Users can
 * therefore add their own report paths in parallel with the flag-driven ones without conflict.
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
        // CLI flag overrides plugin config.
        $input = $container->get(CoverageInput::class);
        $mode = $input->resolveMode() ?? $this->collect;
        if ($mode === CoverageMode::Never) {
            return;
        }

        $activation = $container->has(CoverageActivation::class)
            ? $container->get(CoverageActivation::class)
            : self::createActivation($container);

        // Own reports plus the CLI-flag reports, which the first activating plugin claims for the
        // whole run so multiple instances don't emit them twice.
        $reports = [...$this->reports, ...$activation->claimCliReports($input)];

        // Soft activation: with nothing to write, stay inert. This is how the shadow default with
        // no CLI flags configured contributes nothing.
        if ($reports === []) {
            return;
        }

        $activation->contribute($this->level, $this->testTypes, $reports, $mode);
    }

    private static function createActivation(Container $container): CoverageActivation
    {
        $activation = new CoverageActivation($container);
        $container->set($activation);

        return $activation;
    }
}
