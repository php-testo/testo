<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit;

use Internal\Container\Container;
use Internal\Container\ObjectContainer;
use Testo\Application\Config\ApplicationConfig;
use Testo\Assert;
use Testo\Codecov\CodecovPlugin;
use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Covers;
use Testo\Codecov\Internal\CoverageActivation;
use Testo\Codecov\Internal\CoverageInput;
use Testo\Common\EventListenerCollector;
use Testo\Test;

/**
 * How the coverage depth is resolved when several plugin instances configure against the same run —
 * the shadow default from the application defaults plus whatever `testo.php` declares.
 */
#[Test]
#[Covers(CodecovPlugin::class)]
final class CodecovPluginTest
{
    /**
     * The report flag activates the shadow default, which sits on the `Line` constructor default and
     * claims the CLI reports first. The depth a user declared in `testo.php` must still reach the
     * collector.
     */
    public function configuredLevelSurvivesCliReportFlags(): void
    {
        $container = self::container(self::input(clover: 'build/clover.xml'));

        (new CodecovPlugin())->configure($container);
        (new CodecovPlugin(level: CoverageLevel::Branch))->configure($container);

        Assert::same(self::level($container), CoverageLevel::Branch);
        // Still one report: the second instance brings a level, not a duplicate of the flag report.
        Assert::array(self::read($container, 'reports'))->hasCount(1);
    }

    /**
     * `--coverage-level` is a pin, not another vote in the deepest-wins merge: it wins even when it
     * asks for less than the configured level.
     */
    public function cliLevelOverridesConfiguredLevel(): void
    {
        $container = self::container(self::input(clover: 'build/clover.xml', level: 'line'));

        (new CodecovPlugin())->configure($container);
        (new CodecovPlugin(level: CoverageLevel::Path))->configure($container);

        Assert::same(self::level($container), CoverageLevel::Line);
    }

    public function cliLevelAppliesToFlagOnlyRuns(): void
    {
        $container = self::container(self::input(clover: 'build/clover.xml', level: 'path'));

        (new CodecovPlugin())->configure($container);

        Assert::same(self::level($container), CoverageLevel::Path);
    }

    private static function input(?string $clover = null, ?string $level = null): CoverageInput
    {
        $input = new CoverageInput();
        $input->clover = $clover;
        $input->level = $level;

        return $input;
    }

    private static function level(Container $container): CoverageLevel
    {
        $level = self::read($container, 'level');
        \assert($level instanceof CoverageLevel);

        return $level;
    }

    private static function read(Container $container, string $property): mixed
    {
        $ref = new \ReflectionProperty(CoverageActivation::class, $property);

        return $ref->getValue($container->get(CoverageActivation::class));
    }

    /**
     * Empty `src` keeps driver detection side-effect-free — `XdebugDriver::create()` skips
     * `xdebug_set_filter` when there are no includes.
     */
    private static function container(CoverageInput $input): Container
    {
        $container = new ObjectContainer();
        $container->set($input, CoverageInput::class);
        $container->set(new ApplicationConfig(), ApplicationConfig::class);
        $container->set(new class() implements EventListenerCollector {
            #[\Override]
            public function addListener(string $eventName, callable $callback, int $priority = 0): void {}
        }, EventListenerCollector::class);

        return $container;
    }
}
