<?php

declare(strict_types=1);

namespace Tests\Core\Context;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity;
use Testo\Core\Context\Identity\CaseIdentity;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\Identity\TestIdentity;
use Testo\Core\Definition\CaseDefinition;
use Testo\Expect;
use Testo\Test;

/**
 * Addresses are built by stepping down the run, and carry two independent things: where the node is
 * (stable, comparable) and which run of it is in flight (process-local).
 */
#[Test]
#[Covers(Identity::class)]
#[Covers(SuiteIdentity::class)]
#[Covers(CaseIdentity::class)]
#[Covers(TestIdentity::class)]
final class IdentityTest
{
    public function stepsDownCopyTheFieldsRatherThanNestTheLevels(): void
    {
        $test = self::test();

        // The whole address is flat: reading any part of it never walks a chain of objects.
        Assert::same($test->suite, 'Core/Unit');
        Assert::same($test->case, 'Tests\Foo\BarTest');
        Assert::same($test->type, 'test');
        Assert::same($test->test, 'itWorks');
        Assert::same((string) $test->file, '/app/tests/BarTest.php');
        Assert::null($test->dataProvider);
        Assert::null($test->dataSet);
    }

    public function aDataSetIsAddressedByIndex(): void
    {
        $test = self::test();

        // Provider keys repeat freely (`yield 1 => …` twice is legal), so only the indices tell two
        // data sets of one test apart.
        Assert::same($test->toDataSet(dataProvider: 0, dataSet: 1)->fqn(), 'Tests\Foo\BarTest::itWorks:0:1');
        Assert::same($test->toDataSet(dataProvider: 2, dataSet: 0)->fqn(), 'Tests\Foo\BarTest::itWorks:2:0');
    }

    public function aReDerivedAddressCarriesNoStaleRendering(): void
    {
        $test = self::test();

        // The strings are composed once, in the constructor, so `toDataSet()` has to rebuild rather than
        // copy — otherwise a re-derived address would still render the coordinates it no longer has.
        $moved = $test->toDataSet(dataProvider: 0, dataSet: 1)->toDataSet(dataProvider: 2, dataSet: 3);

        Assert::same($moved->fqn(), 'Tests\Foo\BarTest::itWorks:2:3');

        // Overriding one coordinate keeps the other, and still re-renders.
        Assert::same($test->toDataSet(dataProvider: 0, dataSet: 1)->toDataSet(dataSet: 7)->fqn(), 'Tests\Foo\BarTest::itWorks:0:7');
    }

    public function theFqnNamesCodeAndNothingElse(): void
    {
        $case = (new SuiteIdentity('Core/Unit'))->toCase('Tests\Foo\BarTest', 'test', self::path());
        $test = $case->toTest('itWorks');

        // The same class runs under any suite and any type, and both are filtered separately, so
        // neither belongs in the form that `--filter` and TeamCity consume. Nor does the file.
        Assert::same($case->fqn(), 'Tests\Foo\BarTest');
        Assert::same($test->fqn(), 'Tests\Foo\BarTest::itWorks');
        Assert::same($test->toDataSet(dataProvider: 0, dataSet: 1)->fqn(), 'Tests\Foo\BarTest::itWorks:0:1');
    }

    public function theQualifiedNameDropsTheCoordinatesTheFqnKeeps(): void
    {
        $dataSet = self::test()->toDataSet(dataProvider: 1, dataSet: 4);

        // Consumers that group by test method — coverage entries, the JUnit `classname` — need every
        // data set of one test to answer the same string, so they read this rather than `fqn()`.
        Assert::same($dataSet->qualifiedName(), 'Tests\Foo\BarTest::itWorks');
        Assert::same($dataSet->fqn(), 'Tests\Foo\BarTest::itWorks:1:4');
    }

    public function aTestNameIsRelativeToItsCaseAndAbsoluteWithoutOne(): void
    {
        $withClass = self::test();
        $free = (new SuiteIdentity('Core/Unit'))
            ->toCase(null, 'test', self::path('/app/tests/functions.php'))
            ->toTest('Tests\Foo\itWorksToo');

        // A method is named relative to its class, so the bare name is complete. A free function has no
        // class to be relative to, so it carries its own namespace in the same field — which is also
        // why "a class *and* a namespace" is not a state this type can be put in.
        Assert::same($withClass->fqn(), 'Tests\Foo\BarTest::itWorks');
        Assert::same($free->fqn(), 'Tests\Foo\itWorksToo');

        // The case itself still has no FQN — a file is not one.
        Assert::null($free->case);
    }

    public function aCaseWithoutAClassIsNamedByItsFile(): void
    {
        $case = (new SuiteIdentity('Core/Unit'))->toCase(null, 'test', self::path('/app/tests/functions.php'));

        // No class means no FQN; the file is the only thing that tells two such cases of one suite apart.
        Assert::null($case->fqn());
        Assert::same((string) $case->file, '/app/tests/functions.php');
    }

    #[Covers(CaseInfo::class)]
    public function theFileComesStraightFromTheDefinition(): void
    {
        $definition = new CaseDefinition(
            name: 'CaseInfo',
            type: 'test',
            file: Path::create((new \ReflectionClass(CaseInfo::class))->getFileName()),
            reflection: new \ReflectionClass(CaseInfo::class),
        );

        $case = new CaseInfo($definition, new SuiteIdentity('Core/Context'));

        // One spelling for the whole address, and it is `Path` that guarantees it: a filename reflection
        // reports with the OS separator comes back normalized, so the field cannot name one file two ways.
        Assert::same($case->identity->file, $definition->file);
        Assert::same(\substr_count((string) $case->identity->file, '\\'), 0);
    }

    public function theFileStaysOnTheAddressEvenWithAClass(): void
    {
        $case = (new SuiteIdentity('Core/Unit'))->toCase('Tests\Foo\BarTest', 'test', self::path());

        // A class does name its own file, but resolving that means loading the class — and TeamCity's
        // location hint needs both parts side by side.
        Assert::same((string) $case->file, '/app/tests/BarTest.php');
        Assert::same((string) $case->toTest('itWorks')->file, '/app/tests/BarTest.php');

        // The class still wins for the FQN.
        Assert::same($case->fqn(), 'Tests\Foo\BarTest');
    }

    public function theSuiteFqnIsItsName(): void
    {
        $suite = new SuiteIdentity('Core/Unit');

        // A suite is a configuration entry rather than code: its name is all there is to give, and it
        // is exactly what `--suite` matches.
        Assert::same($suite->fqn(), 'Core/Unit');
    }

    public function distinctRunsGetDistinctRuntimeIds(): void
    {
        $case = (new SuiteIdentity('Core/Unit'))->toCase('Tests\Foo\BarTest', 'test', self::path());

        $first = $case->toTest('itWorks');
        $second = $case->toTest('itWorks');

        // Same address, different runs — which is exactly why the address alone cannot serve as the
        // correlation key for output and events.
        Assert::same($first->fqn(), $second->fqn());
        Assert::notSame($first->runtimeId, $second->runtimeId);
    }

    public function eachDataSetIsARunOfItsOwn(): void
    {
        $test = self::test();

        $first = $test->toDataSet(dataProvider: 0, dataSet: 0);
        $second = $test->toDataSet(dataProvider: 0, dataSet: 1);

        // A data set runs, and so it correlates its own events and its own captured output.
        Assert::same(\count(\array_unique([$test->runtimeId, $first->runtimeId, $second->runtimeId])), 3);
    }

    public function everyDataSetBelongsToTheTestRunItCameFrom(): void
    {
        $test = self::test();

        $first = $test->toDataSet(dataProvider: 0, dataSet: 0);
        $second = $test->toDataSet(dataProvider: 1, dataSet: 0)->toDataSet(dataSet: 3);

        // What holds a batch together in a report — one terminal block, one TeamCity flow. It survives
        // re-deriving, so a data set addressed in two steps is still inside the same test run.
        Assert::same($first->pipelineId, $test->pipelineId);
        Assert::same($second->pipelineId, $test->pipelineId);

        // A test opens its own run, so grouping by this number needs no special case for a test
        // that never had a data set.
        Assert::same($test->pipelineId, $test->runtimeId);
    }

    public function everyLevelPointsAtTheRunItOpenedInside(): void
    {
        $suite = new SuiteIdentity('Core/Unit');
        $case = $suite->toCase('Tests\Foo\BarTest', 'test', self::path());
        $test = $case->toTest('itWorks');
        $dataSet = $test->toDataSet(dataProvider: 0, dataSet: 1);

        // One field at every level, so rebuilding the tree of a run — an IDE's `parentNodeId` — needs
        // neither the order the events arrived in nor knowledge of which level is in hand.
        Assert::null($suite->parentId);
        Assert::same($case->parentId, $suite->runtimeId);
        Assert::same($test->parentId, $case->runtimeId);
        Assert::same($dataSet->parentId, $test->runtimeId);
    }

    public function reDerivingADataSetLeavesItASiblingOfTheOthers(): void
    {
        $test = self::test();

        // Deriving from a data set corrects coordinates rather than nesting a data set inside one, so
        // the test stays the parent of both.
        Assert::same($test->toDataSet(dataProvider: 0, dataSet: 1)->toDataSet(dataSet: 7)->parentId, $test->runtimeId);
    }

    public function everyLevelGetsItsOwnRuntimeId(): void
    {
        $suite = new SuiteIdentity('Core/Unit');
        $case = $suite->toCase('Tests\Foo\BarTest', 'test', self::path());
        $test = $case->toTest('itWorks');

        // One shared sequence behind all three levels, so a suite and a case never collide on a number.
        Assert::same(\count(\array_unique([$suite->runtimeId, $case->runtimeId, $test->runtimeId])), 3);
    }

    public function halfADataSetAddressIsRejected(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        // Without both coordinates the address would claim to be a data set it cannot name.
        new TestIdentity('Core/Unit', 'Tests\Foo\BarTest', 'test', self::path(), 'itWorks', dataProvider: 0);
    }

    private static function test(): TestIdentity
    {
        return (new SuiteIdentity('Core/Unit'))
            ->toCase('Tests\Foo\BarTest', 'test', self::path())
            ->toTest('itWorks');
    }

    private static function path(string $path = '/app/tests/BarTest.php'): Path
    {
        return Path::create($path);
    }
}
