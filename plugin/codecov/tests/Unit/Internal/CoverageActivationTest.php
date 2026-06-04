<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Internal;

use Internal\Container\Container;
use Testo\Application\Config\ApplicationConfig;
use Testo\Assert;
use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Config\CoverageMode;
use Testo\Codecov\Covers;
use Testo\Codecov\Internal\CoverageActivation;
use Testo\Codecov\Internal\CoverageInput;
use Testo\Codecov\Report\CloverReport;
use Testo\Codecov\Report\CoverageReport;
use Testo\Common\EventListenerCollector;
use Testo\Test;

/**
 * Exercises the env-independent merge math of the coordinator (level/mode/testTypes/reports and the
 * once-only CLI-report claim). Driver detection and the actual wiring are covered end-to-end by the
 * suite's coverage runs rather than here, since they depend on a live Xdebug/PCOV driver.
 */
#[Test]
#[Covers(CoverageActivation::class)]
final class CoverageActivationTest
{
    public function claimCliReportsReturnsReportsOnceThenEmpty(): void
    {
        $activation = self::createActivation();

        $input = new CoverageInput();
        $input->clover = 'build/clover.xml';

        $first = $activation->claimCliReports($input);
        $second = $activation->claimCliReports($input);

        Assert::array($first)->hasCount(1);
        Assert::true($first[0] instanceof CloverReport);
        Assert::array($second)->hasCount(0);
    }

    public function contributeKeepsDeepestLevel(): void
    {
        $activation = self::createActivation();
        $activation->contribute(CoverageLevel::Line, [], [self::report()], CoverageMode::IfAvailable);
        $activation->contribute(CoverageLevel::Path, [], [self::report()], CoverageMode::IfAvailable);
        $activation->contribute(CoverageLevel::Branch, [], [self::report()], CoverageMode::IfAvailable);

        Assert::same(self::read($activation, 'level'), CoverageLevel::Path);
    }

    public function contributeKeepsStrongestMode(): void
    {
        $activation = self::createActivation();
        $activation->contribute(CoverageLevel::Line, [], [self::report()], CoverageMode::IfAvailable);
        $activation->contribute(CoverageLevel::Line, [], [self::report()], CoverageMode::Always);

        Assert::same(self::read($activation, 'mode'), CoverageMode::Always);
    }

    public function contributeUnionsTestTypes(): void
    {
        $activation = self::createActivation();
        $activation->contribute(CoverageLevel::Line, ['test'], [self::report()], CoverageMode::IfAvailable);
        $activation->contribute(CoverageLevel::Line, ['bench'], [self::report()], CoverageMode::IfAvailable);

        Assert::same(\array_keys(self::read($activation, 'testTypes')), ['test', 'bench']);
    }

    public function emptyTestTypesWidenToAllAndStick(): void
    {
        $activation = self::createActivation();
        $activation->contribute(CoverageLevel::Line, ['test'], [self::report()], CoverageMode::IfAvailable);
        // An empty contribution means "all types" — it must clear the set and never narrow again.
        $activation->contribute(CoverageLevel::Line, [], [self::report()], CoverageMode::IfAvailable);
        $activation->contribute(CoverageLevel::Line, ['bench'], [self::report()], CoverageMode::IfAvailable);

        Assert::array(self::read($activation, 'testTypes'))->hasCount(0);
        Assert::true(self::read($activation, 'allTestTypes'));
    }

    public function contributeAccumulatesReports(): void
    {
        $activation = self::createActivation();
        $activation->contribute(CoverageLevel::Line, [], [self::report(), self::report()], CoverageMode::IfAvailable);
        $activation->contribute(CoverageLevel::Line, [], [self::report()], CoverageMode::IfAvailable);

        Assert::array(self::read($activation, 'reports'))->hasCount(3);
    }

    /**
     * Two plugin instances (the shadow default + a user-declared one) feed the same coordinator,
     * but the driver — and with it XDebug's engine-level `xdebug_set_filter` — must be resolved
     * exactly once, from the single global source config. The first contribution wins; the rest
     * only add reports/level/types. We prove single resolution by counting source-config lookups.
     */
    public function resolvesDriverOnceAcrossContributions(): void
    {
        $container = self::container();
        $activation = new CoverageActivation($container);

        $activation->contribute(CoverageLevel::Line, ['test'], [self::report()], CoverageMode::IfAvailable);
        $activation->contribute(CoverageLevel::Path, [], [self::report()], CoverageMode::Always);
        $activation->contribute(CoverageLevel::Branch, ['bench'], [self::report()], CoverageMode::IfAvailable);

        Assert::same($container->appConfigLookups, 1);
        Assert::true(self::read($activation, 'driverResolved'));
    }

    private static function createActivation(): CoverageActivation
    {
        return new CoverageActivation(self::container());
    }

    private static function report(): CoverageReport
    {
        return new class() implements CoverageReport {
            #[\Override]
            public function generate(\Testo\Codecov\Result\CoverageResult $result): void {}
        };
    }

    private static function read(CoverageActivation $activation, string $property): mixed
    {
        $ref = new \ReflectionProperty(CoverageActivation::class, $property);
        return $ref->getValue($activation);
    }

    /**
     * Minimal container that only satisfies the coordinator constructor's listener registration.
     */
    private static function container(): Container
    {
        $listeners = new class() implements EventListenerCollector {
            #[\Override]
            public function addListener(string $eventName, callable $callback, int $priority = 0): void {}
        };

        return new class($listeners) implements Container {
            /** Counts how often the source config (used for driver/filter detection) is fetched. */
            public int $appConfigLookups = 0;

            public function __construct(private readonly EventListenerCollector $listeners) {}

            #[\Override]
            public function get(string $id, array $arguments = []): object
            {
                // Default config has empty src, which keeps driver detection side-effect-free
                // (XdebugDriver::create() skips xdebug_set_filter when there are no includes).
                if ($id === ApplicationConfig::class) {
                    $this->appConfigLookups++;
                    return new ApplicationConfig();
                }

                return $this->listeners;
            }

            #[\Override]
            public function has(string $id): bool
            {
                return false;
            }

            #[\Override]
            public function set(object $service, ?string $id = null, bool $destroy = false): void {}

            #[\Override]
            public function make(string $class, array $arguments = []): object
            {
                return $this->listeners;
            }

            #[\Override]
            public function bind(string $id, \Closure|string|array|null $binding = null): void {}

            #[\Override]
            public function scope(\Closure $scope): mixed
            {
                return $scope($this);
            }

            #[\Override]
            public function destroy(): void {}
        };
    }
}
