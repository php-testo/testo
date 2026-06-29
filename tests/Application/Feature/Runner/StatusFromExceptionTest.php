<?php

declare(strict_types=1);

namespace Tests\Application\Feature\Runner;

use Testo\Application\Internal\Runner\TestRunner;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\CancelTest;
use Testo\Core\Exception\SkipTest;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner as TestingRunner;
use Tests\Application\Stub\Runner\PipelineStartingRecorderPlugin;
use Tests\Application\Stub\Runner\StatusFromException;

/**
 * Verifies that exceptions thrown from a test body map to the corresponding
 * {@see Status} on the resulting {@see \Testo\Core\Context\TestResult}.
 */
#[Test]
#[Covers(TestRunner::class)]
#[TestingSuite(
    path: __DIR__ . '/../../Stub/Runner',
    plugins: [PipelineStartingRecorderPlugin::class],
)]
final class StatusFromExceptionTest
{
    public function pipelineStartingEventIsDispatchedForEachTest(): void
    {
        PipelineStartingRecorderPlugin::reset();

        $result = TestingRunner::runTest([StatusFromException::class, 'throwsSkipTest']);

        Assert::contains(PipelineStartingRecorderPlugin::$names, $result->info->name);
    }

    public function skipTestMarksStatusSkipped(): void
    {
        $result = TestingRunner::runTest([StatusFromException::class, 'throwsSkipTest']);

        Assert::same($result->status, Status::Skipped);
        Assert::instanceOf($result->failure, SkipTest::class);
        Assert::same($result->failure->getMessage(), StatusFromException::SKIP_MESSAGE);
    }

    public function cancelTestMarksStatusCancelled(): void
    {
        $result = TestingRunner::runTest([StatusFromException::class, 'throwsCancelTest']);

        Assert::same($result->status, Status::Cancelled);
        Assert::instanceOf($result->failure, CancelTest::class);
        Assert::same($result->failure->getMessage(), StatusFromException::CANCEL_MESSAGE);
    }

    public function genericExceptionStillMarksStatusError(): void
    {
        $result = TestingRunner::runTest([StatusFromException::class, 'throwsGenericException']);

        Assert::same($result->status, Status::Error);
        Assert::instanceOf($result->failure, \RuntimeException::class);
        Assert::same($result->failure->getMessage(), StatusFromException::ERROR_MESSAGE);
    }

    /**
     * Subclasses of {@see SkipTest} must also be recognized — users may want to
     * extend with custom metadata (e.g. MissingExtensionSkip).
     */
    public function subclassOfSkipTestIsRecognized(): void
    {
        $result = TestingRunner::runTest([StatusFromException::class, 'throwsSubclassedSkipTest']);

        Assert::same($result->status, Status::Skipped);
        Assert::instanceOf($result->failure, SkipTest::class);
    }
}
