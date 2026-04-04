<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Dto;

use Testo\Assert;
use Testo\Codecov\Result\FileCoverage;
use Testo\Codecov\Result\LineStatus;
use Testo\Test;

#[Test]
final class FileCoverageTest
{
    public function constructWithLines(): void
    {
        $file = new FileCoverage('/src/Foo.php', [
            5 => LineStatus::Executed,
            6 => LineStatus::NotExecuted,
        ]);

        Assert::same($file->path, '/src/Foo.php');
        Assert::count($file->lines, 2);
        Assert::same($file->functions, []);
    }

    public function mergeExecutedWins(): void
    {
        $a = new FileCoverage('/src/Foo.php', [
            5 => LineStatus::Executed,
            6 => LineStatus::NotExecuted,
            7 => LineStatus::NotExecuted,
        ]);

        $b = new FileCoverage('/src/Foo.php', [
            5 => LineStatus::NotExecuted,
            6 => LineStatus::Executed,
            8 => LineStatus::Dead,
        ]);

        // Act
        $merged = $a->merge($b);

        // Assert
        Assert::same($merged->lines[5], LineStatus::Executed);
        Assert::same($merged->lines[6], LineStatus::Executed);
        Assert::same($merged->lines[7], LineStatus::NotExecuted);
        Assert::same($merged->lines[8], LineStatus::Dead);
    }

    public function mergeReturnsNewInstance(): void
    {
        $a = new FileCoverage('/src/Foo.php', [5 => LineStatus::Executed]);
        $b = new FileCoverage('/src/Foo.php', [6 => LineStatus::Executed]);

        $merged = $a->merge($b);

        Assert::notSame($merged, $a);
        Assert::notSame($merged, $b);
    }

    public function mergeDeadDoesNotOverrideNotExecuted(): void
    {
        $a = new FileCoverage('/src/Foo.php', [5 => LineStatus::NotExecuted]);
        $b = new FileCoverage('/src/Foo.php', [5 => LineStatus::Dead]);

        $merged = $a->merge($b);

        Assert::same($merged->lines[5], LineStatus::NotExecuted);
    }
}
