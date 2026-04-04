<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Dto;

use Testo\Assert;
use Testo\Codecov\Result\LineStatus;
use Testo\Test;

#[Test]
final class LineStatusTest
{
    public function backedValues(): void
    {
        Assert::same(LineStatus::Executed->value, 1);
        Assert::same(LineStatus::NotExecuted->value, -1);
        Assert::same(LineStatus::Dead->value, -2);
    }

    public function executedIsExecutable(): void
    {
        Assert::true(LineStatus::Executed->isExecutable());
    }

    public function notExecutedIsExecutable(): void
    {
        Assert::true(LineStatus::NotExecuted->isExecutable());
    }

    public function deadIsNotExecutable(): void
    {
        Assert::false(LineStatus::Dead->isExecutable());
    }

    public function tryFromValidValues(): void
    {
        Assert::same(LineStatus::tryFrom(1), LineStatus::Executed);
        Assert::same(LineStatus::tryFrom(-1), LineStatus::NotExecuted);
        Assert::same(LineStatus::tryFrom(-2), LineStatus::Dead);
    }

    public function tryFromInvalidReturnsNull(): void
    {
        Assert::null(LineStatus::tryFrom(0));
        Assert::null(LineStatus::tryFrom(99));
    }
}
