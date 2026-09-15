<?php

declare(strict_types=1);

namespace Tests\Skip\Feature;

use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
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
use Testo\Test;
use Testo\Test\TestPlugin;
use Tests\Skip\Stub\SkipStandalone\StandaloneSkippedTest;

/**
 * The standalone contract of `#[Skip]`: no plugin registers {@see SkipInterceptor}, so with
 * `TestPlugin` out of the run and the tests discovered by naming convention, the attribute's own
 * {@see \Testo\Pipeline\Attribute\FallbackInterceptor} declaration is all that skips a
 * method-level case member.
 */
#[Test]
#[Covers(Skip::class)]
#[Covers(SkipInterceptor::class)]
final class SkipFallbackStandaloneTest
{
    public function methodLevelSkipFallsBackWithoutAnyPlugin(): void
    {
        $run = Application::createFromConfig(new ApplicationConfig(
            src: [],
            suites: [
                new SuiteConfig(
                    'SkipStandalone',
                    location: new FinderConfig(include: [__DIR__ . '/../Stub/SkipStandalone']),
                    plugins: SuitePlugins::without(TestPlugin::class)->with(new NamingConventionPlugin()),
                ),
            ],
        ))->run();

        /** @var array<non-empty-string, TestResult> $tests */
        $tests = [];
        foreach ($run as $suite) {
            foreach ($suite as $case) {
                foreach ($case as $test) {
                    $tests[$test->info->name] = $test;
                }
            }
        }

        Assert::count($tests, 2);
        Assert::true(StandaloneSkippedTest::$enabledRan);
        Assert::same($tests['testEnabled']->status, Status::Passed);

        $skipped = $tests['testSkipped'];
        Assert::same($skipped->status, Status::Skipped);
        Assert::instanceOf($skipped->failure, SkipTest::class);
        Assert::same(
            $skipped->failure->getMessage(),
            StandaloneSkippedTest::class . '::testSkipped is skipped via #[Skip] ==> standalone method is skipped',
        );
    }
}
