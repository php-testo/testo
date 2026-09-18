<?php

declare(strict_types=1);

namespace Tests\Skip\Feature;

use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\Plugin\PluginCollection;
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Convention\NamingConventionPlugin;
use Testo\Core\Context\TestResult;
use Testo\Core\Exception\SkipTest;
use Testo\Core\Value\Status;
use Testo\Skip;
use Testo\Skip\Internal\SkipInterceptor;
use Testo\Skip\SkipPlugin;
use Testo\Test;
use Testo\Test\TestPlugin;
use Tests\Skip\Stub\SkipStandalone\StandaloneSkippedTest;

/**
 * The standalone contract of `#[Skip]`: with `TestPlugin` out of the run and the tests discovered
 * by naming convention, the attribute's own {@see \Testo\Pipeline\Attribute\FallbackInterceptor}
 * declaration is all that skips a method-level case member. The Skipped result does not even
 * depend on {@see SkipPlugin}: without it only the lifecycle hooks are left unaware of the skip.
 */
#[Test]
#[Covers(Skip::class)]
#[Covers(SkipInterceptor::class)]
final class SkipFallbackStandaloneTest
{
    public function methodLevelSkipFallsBackWithoutTestPlugin(): void
    {
        $tests = self::run(SuitePlugins::without(TestPlugin::class)->with(new NamingConventionPlugin()));

        Assert::count($tests, 2);
        Assert::true(StandaloneSkippedTest::$enabledRan);
        Assert::same($tests['testEnabled']->status, Status::Passed);
        self::assertSkippedWithReason($tests['testSkipped']);
    }

    public function methodLevelSkipFallsBackWithoutSkipPlugin(): void
    {
        $tests = self::run(
            SuitePlugins::without(TestPlugin::class, SkipPlugin::class)->with(new NamingConventionPlugin()),
        );

        Assert::count($tests, 2);
        Assert::same($tests['testEnabled']->status, Status::Passed);
        self::assertSkippedWithReason($tests['testSkipped']);
    }

    private static function assertSkippedWithReason(TestResult $skipped): void
    {
        Assert::same($skipped->status, Status::Skipped);
        Assert::instanceOf($skipped->failure, SkipTest::class);
        Assert::same(
            $skipped->failure->getMessage(),
            StandaloneSkippedTest::class . '::testSkipped is skipped via #[Skip] ==> standalone method is skipped',
        );
    }

    /**
     * @return array<non-empty-string, TestResult>
     */
    private static function run(PluginCollection $plugins): array
    {
        $run = Application::createFromConfig(new ApplicationConfig(
            src: [],
            suites: [
                new SuiteConfig(
                    'SkipStandalone',
                    location: new FinderConfig(include: [__DIR__ . '/../Stub/SkipStandalone']),
                    plugins: $plugins,
                ),
            ],
        ))->run();

        $tests = [];
        foreach ($run as $suite) {
            foreach ($suite as $case) {
                foreach ($case as $test) {
                    $tests[$test->info->name] = $test;
                }
            }
        }

        return $tests;
    }
}
