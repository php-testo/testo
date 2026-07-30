<?php

declare(strict_types=1);

namespace Tests\Core\Context;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\CaseIdentity;
use Testo\Core\Context\Identity;
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
    public function eachLevelRendersTheWholePathToIt(): void
    {
        $suite = new SuiteIdentity('Core/Unit');
        $case = $suite->toCase('Tests\Foo\BarTest', 'test');
        $test = $case->toTestIdentity('itWorks');

        Assert::same((string) $suite, 'Core/Unit');
        Assert::same((string) $case, 'Core/Unit / Tests\Foo\BarTest [test]');
        Assert::same((string) $test, 'Core/Unit / Tests\Foo\BarTest [test] :: itWorks');
    }

    public function stepsDownCopyTheFieldsRatherThanNestTheLevels(): void
    {
        $test = (new SuiteIdentity('Core/Unit'))->toCase('Tests\Foo\BarTest', 'test')->toTestIdentity('itWorks');

        // The whole address is flat scalars, so reading any part of it never walks a chain of objects.
        Assert::same($test->suite, 'Core/Unit');
        Assert::same($test->case, 'Tests\Foo\BarTest');
        Assert::same($test->type, 'test');
        Assert::same($test->test, 'itWorks');
        Assert::null($test->dataProvider);
        Assert::null($test->dataSet);
    }

    public function aDataSetIsAddressedByIndex(): void
    {
        $test = (new SuiteIdentity('Core/Unit'))->toCase('Tests\Foo\BarTest', 'test')->toTestIdentity('itWorks');

        // Provider keys repeat freely (`yield 1 => …` twice is legal), so only the indices tell two
        // data sets of one test apart.
        Assert::same(
            (string) $test->with(dataProvider: 0, dataSet: 1),
            'Core/Unit / Tests\Foo\BarTest [test] :: itWorks:0:1',
        );
        Assert::same(
            (string) $test->with(dataProvider: 2, dataSet: 0),
            'Core/Unit / Tests\Foo\BarTest [test] :: itWorks:2:0',
        );
    }

    public function aReDerivedAddressCarriesNoStaleRendering(): void
    {
        $test = (new SuiteIdentity('Core/Unit'))->toCase('Tests\Foo\BarTest', 'test')->toTestIdentity('itWorks');

        // Both forms are composed once, in the constructor, so `with()` has to rebuild rather than copy
        // — otherwise a re-derived address would still render the coordinates it no longer has.
        $moved = $test->with(dataProvider: 0, dataSet: 1)->with(dataProvider: 2, dataSet: 3);

        Assert::same($moved->fqn(), 'Tests\Foo\BarTest::itWorks:2:3');
        Assert::same((string) $moved, 'Core/Unit / Tests\Foo\BarTest [test] :: itWorks:2:3');

        // Overriding one coordinate keeps the other, and still re-renders.
        Assert::same($test->with(dataProvider: 0, dataSet: 1)->with(dataSet: 7)->fqn(), 'Tests\Foo\BarTest::itWorks:0:7');
    }

    public function theFqnNamesCodeAndNothingElse(): void
    {
        $suite = new SuiteIdentity('Core/Unit');
        $case = $suite->toCase('Tests\Foo\BarTest', 'test');
        $test = $case->toTestIdentity('itWorks');

        // The same class runs under any suite and any type, and both are filtered separately, so
        // neither belongs in the form that `--filter` and TeamCity consume.
        Assert::same($case->fqn(), 'Tests\Foo\BarTest');
        Assert::same($test->fqn(), 'Tests\Foo\BarTest::itWorks');
        Assert::same($test->with(dataProvider: 0, dataSet: 1)->fqn(), 'Tests\Foo\BarTest::itWorks:0:1');
    }

    public function aFreeFunctionIsQualifiedByItsNamespace(): void
    {
        $case = (new SuiteIdentity('Core/Unit'))->toCase(null, 'test', '/app/tests/functions.php');

        // No class to join with `::`, so the namespace qualifies the function directly. The case itself
        // still has no FQN — a file is not one.
        Assert::null($case->fqn());
        Assert::same($case->toTestIdentity('itWorksToo', 'Tests\Foo')->fqn(), 'Tests\Foo\itWorksToo');

        // A function at global scope has no namespace either; the bare name is the filter's fragment form.
        Assert::same($case->toTestIdentity('itWorksToo')->fqn(), 'itWorksToo');
    }

    public function aCaseWithoutAClassIsNamedByItsFile(): void
    {
        $case = (new SuiteIdentity('Core/Unit'))->toCase(null, 'test', '/app/tests/functions.php');

        // Otherwise two files of free functions in one suite would render the same address.
        Assert::same((string) $case, 'Core/Unit / /app/tests/functions.php [test]');
        Assert::same(
            (string) $case->toTestIdentity('itWorksToo'),
            'Core/Unit / /app/tests/functions.php [test] :: itWorksToo',
        );

        // With neither a class nor a file there is nothing to name the node with, so the segment goes.
        Assert::same((string) (new SuiteIdentity('Core/Unit'))->toCase(null, 'test'), 'Core/Unit [test]');
    }

    #[Covers(CaseInfo::class)]
    public function theFileIsSpelledOneWayWhateverItCameFrom(): void
    {
        $path = (string) Path::create((new \ReflectionClass(CaseInfo::class))->getFileName());

        // Three routes to the same file: reflection reports the OS separator, a located definition
        // carries a path already normalized to `/`, and a case of free functions has only the latter.
        $fromReflection = new CaseInfo(new CaseDefinition(
            name: 'CaseInfo',
            type: 'test',
            reflection: new \ReflectionClass(CaseInfo::class),
        ));
        $fromDefinition = new CaseInfo(new CaseDefinition(name: 'CaseInfo', type: 'test', file: $path));

        Assert::same($fromReflection->identity->file, $path);
        Assert::same($fromDefinition->identity->file, $path);

        // Nothing to take it from at all.
        Assert::null((new CaseInfo(new CaseDefinition(name: 'X', type: 'test')))->identity->file);
    }

    public function theFileStaysOnTheAddressEvenWithAClass(): void
    {
        $case = (new SuiteIdentity('Core/Unit'))->toCase('Tests\Foo\BarTest', 'test', '/app/tests/BarTest.php');

        // A class does name its own file, but resolving that means loading the class — and TeamCity's
        // location hint needs both parts side by side.
        Assert::same($case->file, '/app/tests/BarTest.php');
        Assert::same($case->toTestIdentity('itWorks')->file, '/app/tests/BarTest.php');

        // The class still wins for display and for the FQN.
        Assert::same((string) $case, 'Core/Unit / Tests\Foo\BarTest [test]');
    }

    public function theSuiteIsTheOneLevelWhereBothFormsAgree(): void
    {
        $suite = new SuiteIdentity('Core/Unit');

        // A suite is a configuration entry rather than code: its name is all there is to give.
        Assert::same($suite->fqn(), (string) $suite);
    }

    public function theTypeIsPartOfTheAddress(): void
    {
        $suite = new SuiteIdentity('Core/Unit');

        // One file can define cases of several types, so two of them are not the same case.
        Assert::notSame(
            (string) $suite->toCase('Tests\Foo\BarTest', 'test'),
            (string) $suite->toCase('Tests\Foo\BarTest', 'inline'),
        );
    }

    public function distinctRunsGetDistinctRandomIds(): void
    {
        $case = (new SuiteIdentity('Core/Unit'))->toCase('Tests\Foo\BarTest', 'test');

        $first = $case->toTestIdentity('itWorks');
        $second = $case->toTestIdentity('itWorks');

        // Same address, different runs — which is exactly why the address alone cannot serve as the
        // correlation key for output and events.
        Assert::same((string) $first, (string) $second);
        Assert::notSame($first->randomId, $second->randomId);
    }

    public function withStaysInsideTheRunItCameFrom(): void
    {
        $test = (new SuiteIdentity('Core/Unit'))->toCase('Tests\Foo\BarTest', 'test')->toTestIdentity('itWorks');

        $first = $test->with(dataProvider: 0, dataSet: 0);
        $second = $test->with(dataProvider: 0, dataSet: 1);

        // A data set is a phase of its batch's run, not a run of its own: it shares the report block,
        // the output scope and the TeamCity flow, all keyed by this number.
        Assert::same($first->randomId, $test->randomId);
        Assert::same($second->randomId, $test->randomId);
    }

    public function everyLevelGetsItsOwnRandomId(): void
    {
        $suite = new SuiteIdentity('Core/Unit');
        $case = $suite->toCase('Tests\Foo\BarTest', 'test');
        $test = $case->toTestIdentity('itWorks');

        // One shared sequence behind all three levels, so a suite and a case never collide on a number.
        Assert::same(\count(\array_unique([$suite->randomId, $case->randomId, $test->randomId])), 3);
    }

    public function halfADataSetAddressIsRejected(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        // Without both coordinates the address would claim to be a data set it cannot name.
        new TestIdentity('Core/Unit', 'Tests\Foo\BarTest', 'test', null, 'itWorks', dataProvider: 0);
    }
}
