<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Dto;

use Testo\Assert;
use Testo\Codecov\Result\FileCoverage;
use Testo\Codecov\Result\LineCoverage;
use Testo\Codecov\Result\LineStatus;
use Testo\Test;

#[Test]
final class FileCoverageTest
{
    public function constructWithLines(): void
    {
        $file = new FileCoverage('/src/Foo.php', [
            5 => new LineCoverage(5, LineStatus::Executed),
            6 => new LineCoverage(6, LineStatus::NotExecuted),
        ]);

        Assert::same($file->path, '/src/Foo.php');
        Assert::count($file->lines, 2);
        Assert::same($file->functions, []);
    }

    public function mergeExecutedWins(): void
    {
        $a = new FileCoverage('/src/Foo.php', [
            5 => new LineCoverage(5, LineStatus::Executed),
            6 => new LineCoverage(6, LineStatus::NotExecuted),
            7 => new LineCoverage(7, LineStatus::NotExecuted),
        ]);

        $b = new FileCoverage('/src/Foo.php', [
            5 => new LineCoverage(5, LineStatus::NotExecuted),
            6 => new LineCoverage(6, LineStatus::Executed),
            8 => new LineCoverage(8, LineStatus::Dead),
        ]);

        // Act
        $merged = $a->merge($b);

        // Assert
        Assert::same($merged->lines[5]->status, LineStatus::Executed);
        Assert::same($merged->lines[6]->status, LineStatus::Executed);
        Assert::same($merged->lines[7]->status, LineStatus::NotExecuted);
        Assert::same($merged->lines[8]->status, LineStatus::Dead);
    }

    public function mergeReturnsNewInstance(): void
    {
        $a = new FileCoverage('/src/Foo.php', [5 => new LineCoverage(5, LineStatus::Executed)]);
        $b = new FileCoverage('/src/Foo.php', [6 => new LineCoverage(6, LineStatus::Executed)]);

        $merged = $a->merge($b);

        Assert::notSame($merged, $a);
        Assert::notSame($merged, $b);
    }

    public function mergeDeadDoesNotOverrideNotExecuted(): void
    {
        $a = new FileCoverage('/src/Foo.php', [5 => new LineCoverage(5, LineStatus::NotExecuted)]);
        $b = new FileCoverage('/src/Foo.php', [5 => new LineCoverage(5, LineStatus::Dead)]);

        $merged = $a->merge($b);

        Assert::same($merged->lines[5]->status, LineStatus::NotExecuted);
    }

    public function mergeUnionsTestMethods(): void
    {
        $a = new FileCoverage('/src/Foo.php', [
            5 => new LineCoverage(5, LineStatus::Executed, ['Tests\\FooTest::testA']),
        ]);
        $b = new FileCoverage('/src/Foo.php', [
            5 => new LineCoverage(5, LineStatus::Executed, ['Tests\\FooTest::testB']),
        ]);

        $merged = $a->merge($b);

        Assert::same($merged->lines[5]->testMethods, ['Tests\\FooTest::testA', 'Tests\\FooTest::testB']);
    }

    public function withTestMethodStampsExecutedLines(): void
    {
        $file = new FileCoverage('/src/Foo.php', [
            5 => new LineCoverage(5, LineStatus::Executed),
            6 => new LineCoverage(6, LineStatus::NotExecuted),
            7 => new LineCoverage(7, LineStatus::Dead),
        ]);

        $stamped = $file->withTestMethod('Tests\\FooTest::testA');

        Assert::same($stamped->lines[5]->testMethods, ['Tests\\FooTest::testA']);
        Assert::same($stamped->lines[6]->testMethods, []);
        Assert::same($stamped->lines[7]->testMethods, []);
    }
}
