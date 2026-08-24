<?php

declare(strict_types=1);

namespace Tests\Application\Feature\Runner;

use Internal\Path;
use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\RunConfiguration;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\RunResult;
use Testo\Test;

/**
 * Where a run spent its time, and what it was asked to do — the two things a report cannot derive from
 * results alone. Phases are accumulated durations rather than intervals, because discovery and
 * execution interleave: a suite is scanned, run, and only then is the next one scanned.
 */
#[Test]
#[Covers(Application::class)]
#[Covers(RunConfiguration::class)]
final class RunPhasesTest
{
    public function aRunReportsEveryPhaseInTheOrderItBegan(): void
    {
        $timing = self::run()->timing;

        Assert::same(\array_keys($timing->phases()), ['startup', 'discovery', 'tests', 'teardown']);

        // A real run bootstrapped before the loop and executed tests inside it.
        Assert::true($timing->startup > 0.0);
        Assert::true($timing->tests > 0.0);
    }

    public function theSuiteLoopFitsInsideTheWholeRun(): void
    {
        $timing = self::run()->timing;

        // The loop (discovery + execution) is bracketed by startup before it and teardown after, both
        // measured and non-negative, so it cannot exceed the whole run.
        Assert::true(
            $timing->duration() <= $timing->total(),
            \sprintf('duration = %f, total = %f', $timing->duration(), $timing->total()),
        );
    }

    public function anApplicationBuiltFromAConfigObjectStillOffersARunConfiguration(): void
    {
        $app = self::app();

        // No command line was involved, so there is nothing to report — but a reporter must not have to
        // ask whether the service exists.
        $config = $app->getContainer()->get(RunConfiguration::class);
        Assert::same($config->configFile, null);
        Assert::same($config->options, []);
    }

    public function inputIsCarriedAsGivenWhileTheEnvironmentIsLeftOut(): void
    {
        $app = Application::createFromInput(
            configFile: Path::create(__FILE__),
            inputOptions: ['group' => ['db'], 'json' => false, 'filter' => []],
            inputArguments: ['path' => 'tests/Unit'],
            environment: ['SECRET_TOKEN' => 'do-not-report-me'],
        );

        $config = $app->getContainer()->get(RunConfiguration::class);

        Assert::same((string) $config->configFile, (string) Path::create(__FILE__));
        Assert::same($config->option('group'), ['db']);
        Assert::same($config->arguments['path'], 'tests/Unit');

        // A switched-off flag and an empty array are defaults, not decisions, and listing them would
        // describe a run that never happened.
        Assert::same(\array_keys($config->givenOptions()), ['group']);

        // A report is committed, attached to CI builds and opened by whoever has the link, so the
        // environment the process was handed never enters it.
        Assert::string(\print_r($config, true))->notContains('do-not-report-me');
    }

    private static function app(): Application
    {
        return Application::createFromConfig(new ApplicationConfig(
            src: [],
            suites: [
                new SuiteConfig(
                    'Phases',
                    location: new FinderConfig(include: [__DIR__ . '/../../Stub/Runner']),
                ),
            ],
        ));
    }

    private static function run(): RunResult
    {
        return self::app()->run();
    }
}
