<?php

declare(strict_types=1);

namespace Tests\Core\Value;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Value\Status;
use Testo\Data\DataSet;
use Testo\Test;

#[Test]
#[Covers(Status::class)]
final class StatusTest
{
    #[DataSet([Status::Passed,    true],  'Passed is completed')]
    #[DataSet([Status::Failed,    true],  'Failed is completed')]
    #[DataSet([Status::Error,     true],  'Error is completed')]
    #[DataSet([Status::Risky,     true],  'Risky is completed')]
    #[DataSet([Status::Flaky,     true],  'Flaky is completed')]
    #[DataSet([Status::Cancelled, false], 'Cancelled is not completed')]
    #[DataSet([Status::Skipped,   false], 'Skipped is not completed')]
    #[DataSet([Status::Aborted,   false], 'Aborted is not completed')]
    public function isCompletedReflectsTerminalState(Status $status, bool $expected): void
    {
        Assert::same($status->isCompleted(), $expected);
    }

    #[DataSet([Status::Passed,    true],  'Passed is successful')]
    #[DataSet([Status::Flaky,     true],  'Flaky is successful')]
    #[DataSet([Status::Failed,    false], 'Failed is not successful')]
    #[DataSet([Status::Error,     false], 'Error is not successful')]
    #[DataSet([Status::Risky,     false], 'Risky is not successful')]
    #[DataSet([Status::Skipped,   false], 'Skipped is not successful')]
    #[DataSet([Status::Cancelled, false], 'Cancelled is not successful')]
    #[DataSet([Status::Aborted,   false], 'Aborted is not successful')]
    public function isSuccessfulOnlyForPassedAndFlaky(Status $status, bool $expected): void
    {
        Assert::same($status->isSuccessful(), $expected);
    }

    #[DataSet([Status::Failed,    true],  'Failed is a failure')]
    #[DataSet([Status::Error,     true],  'Error is a failure')]
    #[DataSet([Status::Passed,    false], 'Passed is not a failure')]
    #[DataSet([Status::Flaky,     false], 'Flaky is not a failure')]
    #[DataSet([Status::Risky,     false], 'Risky is not a failure')]
    #[DataSet([Status::Skipped,   false], 'Skipped is not a failure')]
    #[DataSet([Status::Cancelled, false], 'Cancelled is not a failure')]
    #[DataSet([Status::Aborted,   false], 'Aborted is not a failure')]
    public function isFailureOnlyForFailedAndError(Status $status, bool $expected): void
    {
        Assert::same($status->isFailure(), $expected);
    }
}
