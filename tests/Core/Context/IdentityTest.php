<?php

declare(strict_types=1);

namespace Tests\Core\Context;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\Identity\CaseIdentity;
use Testo\Core\Context\Identity;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\Identity\TestIdentity;
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
        Assert::null($test->provider);
        Assert::null($test->dataSet);
    }

    public function aDataSetIsAddressedByIndex(): void
    {
        $test = (new SuiteIdentity('Core/Unit'))->toCase('Tests\Foo\BarTest', 'test')->toTestIdentity('itWorks');

        // Provider keys repeat freely (`yield 1 => …` twice is legal), so only the indices tell two
        // data sets of one test apart.
        Assert::same(
            (string) $test->with(provider: 0, dataSet: 1),
            'Core/Unit / Tests\Foo\BarTest [test] :: itWorks:0:1',
        );
        Assert::same(
            (string) $test->with(provider: 2, dataSet: 0),
            'Core/Unit / Tests\Foo\BarTest [test] :: itWorks:2:0',
        );
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

        $first = $test->with(provider: 0, dataSet: 0);
        $second = $test->with(provider: 0, dataSet: 1);

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
        new TestIdentity('Core/Unit', 'Tests\Foo\BarTest', 'test', 'itWorks', provider: 0);
    }
}
