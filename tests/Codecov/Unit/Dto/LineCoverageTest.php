<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Dto;

use Testo\Assert;
use Testo\Codecov\Result\LineCoverage;
use Testo\Codecov\Result\LineStatus;
use Testo\Test;

#[Test]
final class LineCoverageTest
{
    public function defaultsToEmptyTestMethods(): void
    {
        $line = new LineCoverage(5, LineStatus::Executed);

        Assert::same($line->line, 5);
        Assert::same($line->status, LineStatus::Executed);
        Assert::same($line->testMethods, []);
    }

    public function withTestMethodOnExecutedLineAppends(): void
    {
        $line = new LineCoverage(5, LineStatus::Executed);

        $stamped = $line->withTestMethod('Tests\\FooTest::testA');

        Assert::same($stamped->testMethods, ['Tests\\FooTest::testA']);
    }

    public function withTestMethodOnNonExecutedLineIsNoop(): void
    {
        $line = new LineCoverage(5, LineStatus::NotExecuted);

        $stamped = $line->withTestMethod('Tests\\FooTest::testA');

        Assert::same($stamped, $line);
    }

    public function withTestMethodOnDeadLineIsNoop(): void
    {
        $line = new LineCoverage(5, LineStatus::Dead);

        $stamped = $line->withTestMethod('Tests\\FooTest::testA');

        Assert::same($stamped, $line);
    }

    public function withTestMethodIsIdempotent(): void
    {
        $line = (new LineCoverage(5, LineStatus::Executed))
            ->withTestMethod('Tests\\FooTest::testA');

        $stamped = $line->withTestMethod('Tests\\FooTest::testA');

        Assert::same($stamped, $line);
    }

    public function mergeUnionsTestMethodsPreservingOrder(): void
    {
        $a = new LineCoverage(5, LineStatus::Executed, ['Tests\\FooTest::testA', 'Tests\\FooTest::testB']);
        $b = new LineCoverage(5, LineStatus::Executed, ['Tests\\FooTest::testB', 'Tests\\FooTest::testC']);

        $merged = $a->merge($b);

        Assert::same($merged->testMethods, [
            'Tests\\FooTest::testA',
            'Tests\\FooTest::testB',
            'Tests\\FooTest::testC',
        ]);
    }

    public function mergeExecutedWinsOverNotExecuted(): void
    {
        $a = new LineCoverage(5, LineStatus::NotExecuted);
        $b = new LineCoverage(5, LineStatus::Executed, ['Tests\\FooTest::testA']);

        $merged = $a->merge($b);

        Assert::same($merged->status, LineStatus::Executed);
        Assert::same($merged->testMethods, ['Tests\\FooTest::testA']);
    }

    public function mergeDeadDoesNotOverrideNotExecuted(): void
    {
        $a = new LineCoverage(5, LineStatus::NotExecuted);
        $b = new LineCoverage(5, LineStatus::Dead);

        $merged = $a->merge($b);

        Assert::same($merged->status, LineStatus::NotExecuted);
    }
}
