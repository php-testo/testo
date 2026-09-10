<?php

declare(strict_types=1);

namespace Tests\Core\Definition;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Definition\TestDefinitions;
use Testo\Test;

/**
 * The case's definitions live in one flat collection; a definition's role (test vs non-test) and
 * whether it is active are mutable flags, and the accessors slice the collection over them.
 */
#[Test]
#[Covers(TestDefinitions::class)]
#[Covers(TestDefinition::class)]
final class TestDefinitionsTest
{
    public function defineAddsAnActiveTest(): void
    {
        $definitions = new TestDefinitions();
        $definition = $definitions->define(new \ReflectionFunction('strlen'));

        Assert::true($definition->isTest);
        Assert::true($definition->active);
        Assert::array($definitions->getTests())->hasKeys('strlen');
        Assert::array($definitions->filter(isTest: false))->hasCount(0);
    }

    public function defineWithIsTestFalseAddsANonTest(): void
    {
        $definitions = new TestDefinitions();
        $definition = $definitions->define(new \ReflectionFunction('strlen'), isTest: false);

        Assert::false($definition->isTest);
        Assert::true($definition->active);
        Assert::array($definitions->getTests())->hasCount(0);
        Assert::array($definitions->filter(isTest: false))->hasKeys('strlen');
    }

    /**
     * Registration is idempotent per short name: a second finder registering the same function
     * gets the definition the first one created, with any flags set on it in the meantime.
     */
    public function redefiningReturnsTheExistingDefinition(): void
    {
        $definitions = new TestDefinitions();
        $first = $definitions->define(new \ReflectionFunction('strlen'));
        $first->active = false;

        $second = $definitions->define(new \ReflectionFunction('strlen'));

        Assert::same($second, $first);
        Assert::false($second->active);
        Assert::array($definitions->all())->hasCount(1);
    }

    /**
     * A non-test registration never demotes a test another finder registered: the function stays
     * a test whichever order the finders run in.
     */
    public function nonTestRegistrationDoesNotDemoteAnExistingTest(): void
    {
        $definitions = new TestDefinitions();
        $definitions->define(new \ReflectionFunction('strlen'));

        $definitions->define(new \ReflectionFunction('strlen'), isTest: false);

        Assert::array($definitions->getTests())->hasKeys('strlen');
        Assert::array($definitions->filter(isTest: false))->hasCount(0);
    }

    /**
     * A test registration wins over an earlier non-test one: a function some finder recorded as a
     * helper becomes a test as soon as another finder recognises it.
     */
    public function testRegistrationPromotesAnExistingNonTest(): void
    {
        $definitions = new TestDefinitions();
        $definitions->define(new \ReflectionFunction('strlen'), isTest: false);

        $definitions->define(new \ReflectionFunction('strlen'));

        Assert::array($definitions->getTests())->hasKeys('strlen');
        Assert::array($definitions->filter(isTest: false))->hasCount(0);
    }

    public function flippingIsTestMovesADefinitionOutOfTheTestSet(): void
    {
        $definitions = new TestDefinitions();
        $definition = $definitions->define(new \ReflectionFunction('strlen'));

        $definition->isTest = false;

        Assert::array($definitions->filter(isTest: false))->hasKeys('strlen');
        Assert::array($definitions->getTests())->hasCount(0);
    }

    /**
     * Deactivating a test drops it from the run without discarding it: it leaves the active test
     * set yet is not reclassified as a non-test — this is how filtering narrows a case.
     */
    public function deactivatingATestDropsItFromTheActiveTestSetOnly(): void
    {
        $definitions = new TestDefinitions();
        $definition = $definitions->define(new \ReflectionFunction('strlen'));

        $definition->active = false;

        Assert::array($definitions->getTests())->hasCount(0);
        Assert::array($definitions->getTests(active: false))->hasKeys('strlen');
        Assert::array($definitions->getTests(active: null))->hasKeys('strlen');
        Assert::array($definitions->filter(isTest: false))->hasCount(0);
    }

    /**
     * `all()` returns every definition, and `filter()` slices it over both flags — a null
     * constraint matches either value.
     */
    public function allReturnsEverythingAndFilterSlicesOverBothFlags(): void
    {
        $definitions = new TestDefinitions();
        $definitions->define(new \ReflectionFunction('strlen'));                 # test, active
        $definitions->define(new \ReflectionFunction('strrev'))->active = false; # test, inactive
        $definitions->define(new \ReflectionFunction('trim'), isTest: false);    # non-test, active

        Assert::array($definitions->all())->hasCount(3);

        Assert::array($definitions->filter(isTest: true))
            ->hasKeys('strlen', 'strrev')
            ->doesNotHaveKeys('trim');
        Assert::array($definitions->filter(active: true))
            ->hasKeys('strlen', 'trim')
            ->doesNotHaveKeys('strrev');
        Assert::array($definitions->filter(isTest: true, active: true))->hasKeys('strlen');
        Assert::array($definitions->filter(isTest: false, active: true))->hasKeys('trim');
    }

    public function fromArrayPreservesKeysAndSortReordersInPlace(): void
    {
        $definitions = TestDefinitions::fromArray(
            strrev: new TestDefinition(new \ReflectionFunction('strrev')),
            strlen: new TestDefinition(new \ReflectionFunction('strlen')),
        );

        $definitions->sort(static fn(TestDefinition $a, TestDefinition $b): int =>
            $a->reflection->getShortName() <=> $b->reflection->getShortName());

        Assert::same(\array_keys($definitions->getTests()), ['strlen', 'strrev']);
    }
}
