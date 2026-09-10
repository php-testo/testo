<?php

declare(strict_types=1);

namespace Tests\Filter\Feature;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\Attribute\AssertMethod;
use Testo\Core\Context\TestResult;
use Testo\Filter\Internal\FilterInterceptor;
use Testo\Filter\Internal\SuiteFilterInterceptor;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Filter\Unit\Fixture\Suite\Alpha\AlphaTest;
use Tests\Filter\Unit\Fixture\Suite\Beta\BetaTest;

/**
 * End-to-end checks for the `--suite` and `--path` options (emulated via {@see TestingSuite::$options})
 * through a real application run: the suite list pipeline, the file pre-filter, and the suite scope
 * in between. The testing suite is named `Testing` and spans both fixture directories.
 */
#[Test]
#[Covers(SuiteFilterInterceptor::class)]
#[Covers(FilterInterceptor::class)]
final class SuiteFilterFeatureTest
{
    private const ROOT = __DIR__ . '/../Unit/Fixture/Suite';

    #[TestingSuite(path: self::ROOT, options: ['suite' => ['Testing']])]
    public function selectedSuiteNameRunsTheSuite(): void
    {
        Assert::instanceOf(TestRunner::runTest([AlphaTest::class, 'alpha']), TestResult::class);
        Assert::instanceOf(TestRunner::runTest([BetaTest::class, 'beta']), TestResult::class);
    }

    #[TestingSuite(path: self::ROOT, options: ['suite' => ['Other']])]
    public function unselectedSuiteNameRunsNothing(): void
    {
        $this->assertDidNotRun([AlphaTest::class, 'alpha']);
        $this->assertDidNotRun([BetaTest::class, 'beta']);
    }

    #[TestingSuite(path: self::ROOT, options: ['path' => [self::ROOT . '/Alpha']])]
    public function pathToADirectoryRunsOnlyThatDirectory(): void
    {
        Assert::instanceOf(TestRunner::runTest([AlphaTest::class, 'alpha']), TestResult::class);
        $this->assertDidNotRun([BetaTest::class, 'beta']);
    }

    #[TestingSuite(path: self::ROOT, options: ['path' => [self::ROOT . '/Beta/BetaTest.php']])]
    public function pathToAFileRunsOnlyThatFile(): void
    {
        Assert::instanceOf(TestRunner::runTest([BetaTest::class, 'beta']), TestResult::class);
        $this->assertDidNotRun([AlphaTest::class, 'alpha']);
    }

    #[TestingSuite(path: self::ROOT . '/Alpha', options: ['path' => [self::ROOT]])]
    public function pathAboveTheSuiteRootRunsTheWholeSuite(): void
    {
        Assert::instanceOf(TestRunner::runTest([AlphaTest::class, 'alpha']), TestResult::class);
    }

    #[TestingSuite(path: self::ROOT, options: ['path' => [__DIR__ . '/../Unit/Fixture/GroupedTestClass.php']])]
    public function pathOutsideTheSuiteRunsNothing(): void
    {
        $this->assertDidNotRun([AlphaTest::class, 'alpha']);
        $this->assertDidNotRun([BetaTest::class, 'beta']);
    }

    /**
     * {@see TestRunner::runTest()} throws when the requested test is absent from the run.
     *
     * @param array{class-string, non-empty-string} $test
     */
    #[AssertMethod]
    private function assertDidNotRun(array $test): void
    {
        try {
            TestRunner::runTest($test);
        } catch (\Throwable) {
            Assert::true(true);
            return;
        }

        Assert::true(false, "Test `{$test[0]}::{$test[1]}` should have been filtered out but it ran.");
    }
}
