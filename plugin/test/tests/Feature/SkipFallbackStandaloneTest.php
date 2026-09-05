<?php

declare(strict_types=1);

namespace Tests\Test\Feature;

use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\Plugin\SuitePlugins;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Convention\NamingConventionPlugin;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Test\Internal\SkipInterceptor;
use Testo\Test\Skip;
use Testo\Test\TestPlugin;

/**
 * The standalone contract of `#[Skip]`: with `TestPlugin` not registered, the attribute's
 * {@see \Testo\Pipeline\Attribute\FallbackInterceptor} declaration alone parks a class-level
 * catalog (tests are discovered by naming convention, so no `#[Test]` attribute is involved).
 */
#[Test]
#[Covers(Skip::class)]
#[Covers(SkipInterceptor::class)]
final class SkipFallbackStandaloneTest
{
    public function classLevelSkipFallsBackWithoutTestPlugin(): void
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

        /** @var list<TestResult> $tests */
        $tests = [];
        foreach ($run as $suite) {
            foreach ($suite as $case) {
                foreach ($case as $test) {
                    $tests[] = $test;
                }
            }
        }

        # Exactly one result per stub test: the fallback spawn does not duplicate delivery.
        Assert::count($tests, 2);
        foreach ($tests as $test) {
            Assert::same($test->status, Status::Skipped);
            Assert::true(\str_contains((string) $test->failure?->getMessage(), ' ==> '));
        }
    }
}
