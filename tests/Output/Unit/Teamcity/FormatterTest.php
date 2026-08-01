<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Teamcity;

use Internal\Path;
use Testo\Assert;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\Identity\TestIdentity;
use Testo\Output\Teamcity\Teamcity\Formatter;
use Testo\Test;

#[Test]
final class FormatterTest
{
    public function aCaseHangsUnderItsSuiteRatherThanUnderWhateverOpenedLast(): void
    {
        $suite = new SuiteIdentity('Core/Unit');
        $case = $suite->toCase('Tests\Foo\BarTest', 'test', Path::create('/app/tests/BarTest.php'));

        $msg = Formatter::suiteStarted('BarTest', $case);

        // The tree is stated, not implied by the order messages arrive in — which is the only thing that
        // survives concurrency, where two nodes are open at once.
        Assert::string($msg)->contains("nodeId='{$case->runtimeId}'");
        Assert::string($msg)->contains("parentNodeId='{$suite->runtimeId}'");
    }

    public function aSuiteOfTheRunHangsUnderTheRoot(): void
    {
        $suite = new SuiteIdentity('Core/Unit');

        $msg = Formatter::suiteStarted('Core/Unit', $suite);

        // Nothing sits above a suite, and the id-based protocol fixes the root node at 0.
        Assert::string($msg)->contains("nodeId='{$suite->runtimeId}'");
        Assert::string($msg)->contains("parentNodeId='0'");

        // A suite is a configuration entry rather than code, so there is nothing to point an editor at;
        // and nothing else runs alongside it, so it needs no flow of its own.
        Assert::string($msg)->notContains('locationHint');
        Assert::string($msg)->notContains('flowId');
    }

    public function aDataSetNamesTheBatchItCameFrom(): void
    {
        $test = self::test();
        $dataSet = $test->toDataSet(dataProvider: 0, dataSet: 1);

        $msg = Formatter::testStarted('Dataset #0:1 [x]', identity: $dataSet);

        // Its own node, under the batch's — and in the batch's flow, so the row lands inside the nested
        // suite the batch opened.
        Assert::string($msg)->contains("nodeId='{$dataSet->runtimeId}'");
        Assert::string($msg)->contains("parentNodeId='{$test->runtimeId}'");
        Assert::string($msg)->contains("flowId='{$test->pipelineId}'");
    }

    public function aFinishedMessageNamesTheNodeItCloses(): void
    {
        $test = self::test();

        $msg = Formatter::testFinished('itWorks', 12, $test);

        // A consumer closes the node by id, so a finish never lands on the wrong one when two tests are
        // open at once.
        Assert::string($msg)->contains("nodeId='{$test->runtimeId}'");
        Assert::string($msg)->contains("duration='12'");
    }

    public function testFailedWithoutComparisonHasNoExtraAttributes(): void
    {
        $msg = Formatter::testFailed(
            name: 'myTest',
            message: 'something failed',
        );

        Assert::string($msg)->contains("name='myTest'");
        Assert::string($msg)->contains("message='something failed'");
        Assert::string($msg)->notContains("type=");
        Assert::string($msg)->notContains("expected=");
        Assert::string($msg)->notContains("actual=");
    }

    public function testFailedWithComparisonFailureCarriesTypeAndDiffAttributes(): void
    {
        $msg = Formatter::testFailed(
            name: 'myTest',
            message: 'values differ',
            type: 'comparisonFailure',
            expected: 'one',
            actual: 'two',
        );

        Assert::string($msg)->contains("type='comparisonFailure'");
        Assert::string($msg)->contains("expected='one'");
        Assert::string($msg)->contains("actual='two'");
    }

    public function testStartedEmitsDescriptionAsMetainfo(): void
    {
        $msg = Formatter::testStarted(name: 'myTest', description: 'Verifies the widget');

        Assert::string($msg)->contains("name='myTest'");
        Assert::string($msg)->contains("metainfo='Verifies the widget'");
    }

    public function testStartedOmitsMetainfoWhenNoDescription(): void
    {
        $msg = Formatter::testStarted(name: 'myTest');

        Assert::string($msg)->notContains('metainfo=');
    }

    public function testFailedEscapesSpecialCharactersInExpectedAndActual(): void
    {
        $msg = Formatter::testFailed(
            name: 'myTest',
            message: 'fail',
            type: 'comparisonFailure',
            expected: "line1\nline2",
            actual: "[item|with'quote]",
        );

        Assert::string($msg)->contains("expected='line1|nline2'");
        Assert::string($msg)->contains("actual='|[item||with|'quote|]'");
    }

    private static function test(): TestIdentity
    {
        return (new SuiteIdentity('Core/Unit'))
            ->toCase('Tests\Foo\BarTest', 'test', Path::create('/app/tests/BarTest.php'))
            ->toTest('itWorks');
    }
}
