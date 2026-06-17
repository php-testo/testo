<?php

declare(strict_types=1);

namespace Tests\Filter\Feature;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\TestResult;
use Testo\Filter\Internal\FilterInterceptor;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Filter\Unit\Fixture\GroupedTestClass;
use Tests\Filter\Unit\Fixture\OtherGroupedTestClass;

/**
 * End-to-end checks for the `--filter` option (emulated via {@see TestingSuite::$options}),
 * exercising the three name formats understood by {@see FilterInterceptor}: bare fragment,
 * `Class::method`, and fully-qualified name.
 *
 * Fixture file holds two cases:
 * - {@see GroupedTestClass}: dbTest, slowTest, plainTest, multiTest
 * - {@see OtherGroupedTestClass}: apiTest, ungrouped
 */
#[Test]
#[Covers(FilterInterceptor::class)]
final class NameFilterFeatureTest
{
    #[TestingSuite(
        path: __DIR__ . '/../Unit/Fixture/GroupedTestClass.php',
        options: ['filter' => ['dbTest']],
    )]
    public function fragmentMatchesASingleMethodByShortName(): void
    {
        Assert::instanceOf(TestRunner::runTest([GroupedTestClass::class, 'dbTest']), TestResult::class);
        $this->assertDidNotRun([GroupedTestClass::class, 'slowTest']);
        $this->assertDidNotRun([OtherGroupedTestClass::class, 'apiTest']);
    }

    #[TestingSuite(
        path: __DIR__ . '/../Unit/Fixture/GroupedTestClass.php',
        options: ['filter' => ['GroupedTestClass::slowTest']],
    )]
    public function classMethodFormatMatchesExactlyOneMethod(): void
    {
        Assert::instanceOf(TestRunner::runTest([GroupedTestClass::class, 'slowTest']), TestResult::class);
        $this->assertDidNotRun([GroupedTestClass::class, 'dbTest']);
    }

    #[TestingSuite(
        path: __DIR__ . '/../Unit/Fixture/GroupedTestClass.php',
        options: ['filter' => ['GroupedTestClass']],
    )]
    public function classNameFragmentSelectsTheWholeCase(): void
    {
        # Bare class name matches the case: all of its methods run.
        Assert::instanceOf(TestRunner::runTest([GroupedTestClass::class, 'dbTest']), TestResult::class);
        Assert::instanceOf(TestRunner::runTest([GroupedTestClass::class, 'plainTest']), TestResult::class);
        # The other case is excluded (word-boundary match does not catch OtherGroupedTestClass).
        $this->assertDidNotRun([OtherGroupedTestClass::class, 'apiTest']);
    }

    #[TestingSuite(
        path: __DIR__ . '/../Unit/Fixture/GroupedTestClass.php',
        options: ['filter' => ['Tests\Filter\Unit\Fixture\OtherGroupedTestClass::apiTest']],
    )]
    public function fullyQualifiedClassMethodMatches(): void
    {
        Assert::instanceOf(TestRunner::runTest([OtherGroupedTestClass::class, 'apiTest']), TestResult::class);
        $this->assertDidNotRun([OtherGroupedTestClass::class, 'ungrouped']);
        $this->assertDidNotRun([GroupedTestClass::class, 'dbTest']);
    }

    #[TestingSuite(
        path: __DIR__ . '/../Unit/Fixture/GroupedTestClass.php',
        options: ['filter' => ['noSuchMethod']],
    )]
    public function nonMatchingFilterRunsNothing(): void
    {
        $this->assertDidNotRun([GroupedTestClass::class, 'dbTest']);
        $this->assertDidNotRun([OtherGroupedTestClass::class, 'apiTest']);
    }

    #[TestingSuite(
        path: __DIR__ . '/../Unit/Fixture/GroupedTestClass.php',
        options: ['filter' => ['dbTes']],
    )]
    public function prefixDoesNotMatchBecauseOfEndAnchor(): void
    {
        # The match is anchored to the end (`\b...$`): a prefix of a method name must not match.
        $this->assertDidNotRun([GroupedTestClass::class, 'dbTest']);
    }

    #[TestingSuite(
        path: __DIR__ . '/../Unit/Fixture/GroupedTestClass.php',
        options: ['filter' => ['bTest']],
    )]
    public function midWordSuffixDoesNotMatchBecauseOfWordBoundary(): void
    {
        # `\bbTest$` requires a word boundary before `bTest`; inside `dbTest` there is none.
        $this->assertDidNotRun([GroupedTestClass::class, 'dbTest']);
    }

    #[TestingSuite(
        path: __DIR__ . '/../Unit/Fixture/GroupedTestClass.php',
        options: ['filter' => ['db.est']],
    )]
    public function regexMetacharactersAreEscaped(): void
    {
        # The needle is passed through preg_quote, so `.` is literal and must not match `dbTest`.
        $this->assertDidNotRun([GroupedTestClass::class, 'dbTest']);
    }

    /**
     * {@see TestRunner::runTest()} throws when the requested test is absent from the run,
     * i.e. it was filtered out. Any throwable counts.
     *
     * @param array{class-string, non-empty-string} $test
     */
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
