<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Feature;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Value\Status;
use Testo\Lifecycle\Internal\LifecycleInterceptor;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Tests\Lifecycle\Stub\FullySkipped\FullySkippedClassStub;
use Tests\Lifecycle\Stub\FullySkipped\FullySkippedFunctionState;

/**
 * End-to-end regression test for {@see LifecycleInterceptor}: a case whose every test is flagged
 * skipped ahead of the run — here by `#[Skip]` from `testo/skip` — has nothing to set up, so none
 * of its hooks fire, `#[BeforeClass]`/`#[AfterClass]` included.
 *
 * The skipped tests stay active (the case is still located, run and reported), which is why the
 * interceptor has to look at the skipped flag and not only at the active test set. Both case
 * shapes are pinned here through the real pipeline: the methods of a class-based case and the
 * free functions of a function-based one.
 */
#[Test]
#[Covers(LifecycleInterceptor::class)]
#[TestingSuite(path: __DIR__ . '/../Stub/FullySkipped')]
final class FullySkippedCaseFeatureTest
{
    public function __construct()
    {
        # Functions are not autoloadable: load the stub so TestRunner::runTest() can resolve the
        # function names below. The pipeline re-includes the same file (include_once) when it runs.
        require_once __DIR__ . '/../Stub/FullySkipped/fully_skipped_functions.php';
    }

    /**
     * The function-based case shape: no hook of any kind fires, and the skipped tests are still
     * reported.
     */
    public function noHookRunsForAFullySkippedFunctionCase(): void
    {
        $beforeClass = FullySkippedFunctionState::$beforeClassCalls;
        $afterClass = FullySkippedFunctionState::$afterClassCalls;
        $beforeTest = FullySkippedFunctionState::$beforeTestCalls;
        $afterTest = FullySkippedFunctionState::$afterTestCalls;

        $result = TestRunner::runTest('Tests\Lifecycle\Stub\FullySkipped\skippedFnOne');

        Assert::same($result->status, Status::Skipped);
        Assert::same(FullySkippedFunctionState::$beforeClassCalls - $beforeClass, 0);
        Assert::same(FullySkippedFunctionState::$afterClassCalls - $afterClass, 0);
        Assert::same(FullySkippedFunctionState::$beforeTestCalls - $beforeTest, 0);
        Assert::same(FullySkippedFunctionState::$afterTestCalls - $afterTest, 0);
    }

    /**
     * The class-based analog: the class-level hooks stay silent for a fully skipped class.
     */
    public function noHookRunsForAFullySkippedClassCase(): void
    {
        $beforeClass = FullySkippedClassStub::$beforeClassCalls;
        $afterClass = FullySkippedClassStub::$afterClassCalls;

        $result = TestRunner::runTest([FullySkippedClassStub::class, 'skipped']);

        Assert::same($result->status, Status::Skipped);
        Assert::same(FullySkippedClassStub::$beforeClassCalls - $beforeClass, 0);
        Assert::same(FullySkippedClassStub::$afterClassCalls - $afterClass, 0);
    }
}
