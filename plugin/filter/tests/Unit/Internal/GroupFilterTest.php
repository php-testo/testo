<?php

declare(strict_types=1);

namespace Tests\Filter\Unit\Internal;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Filter;
use Testo\Filter\Internal\FilterInterceptor;
use Testo\Test;
use Testo\Test\Internal\TestoAttributesLocatorInterceptor;
use Testo\Tokenizer\DefinitionLocator;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;
use Tests\Filter\Unit\Fixture\GroupedTestClass;

/**
 * Verifies the {@see \Testo\Group} filtering of {@see FilterInterceptor::locateTestCases()}.
 *
 * Effective group sets of the fixture (class-level `integration` is inherited):
 * - dbTest:    integration, db
 * - slowTest:  integration, slow
 * - plainTest: integration
 * - multiTest: integration, db, fast
 */
#[Test]
#[Covers(FilterInterceptor::class)]
final class GroupFilterTest
{
    public function includeSingleGroupKeepsOnlyMatchingTests(): void
    {
        Assert::array($this->select(new Filter(groups: ['db'])))
            ->hasCount(2)
            ->contains('dbTest')
            ->contains('multiTest')
            ->notContains('slowTest')
            ->notContains('plainTest');
    }

    public function includeMultipleGroupsUsesOrLogic(): void
    {
        Assert::array($this->select(new Filter(groups: ['slow', 'fast'])))
            ->hasCount(2)
            ->contains('slowTest')
            ->contains('multiTest')
            ->notContains('dbTest')
            ->notContains('plainTest');
    }

    public function classLevelGroupPropagatesToEveryTest(): void
    {
        # `integration` is declared only on GroupedTestClass and must not leak into
        # OtherGroupedTestClass (apiTest/ungrouped), so exactly its four methods survive.
        Assert::same(
            $this->select(new Filter(groups: ['integration'])),
            ['dbTest', 'multiTest', 'plainTest', 'slowTest'],
        );
    }

    public function groupMatchingOnlyTheSecondCaseStillSurvives(): void
    {
        # The first case yields no match and is skipped; the interceptor must continue
        # to the second case rather than stop. `api` lives only on OtherGroupedTestClass.
        Assert::same($this->select(new Filter(groups: ['api'])), ['apiTest']);
    }

    public function nameMatchingOnlyTheSecondCaseStillSurvives(): void
    {
        # The name filter matches nothing in the first case (empty name-match -> skipped),
        # while the second case matches: the interceptor must keep iterating past the first.
        Assert::same($this->select(new Filter(names: ['apiTest'])), ['apiTest']);
    }

    public function excludeDropsMatchingTests(): void
    {
        # `slow` exists only on GroupedTestClass::slowTest; every other test survives.
        Assert::array($this->select(new Filter(excludeGroups: ['slow'])))
            ->hasCount(5)
            ->notContains('slowTest')
            ->contains('dbTest')
            ->contains('plainTest')
            ->contains('multiTest')
            ->contains('apiTest')
            ->contains('ungrouped');
    }

    public function excludeTakesPrecedenceOverInclude(): void
    {
        # multiTest is in both `db` (include) and `fast` (exclude) -> excluded.
        Assert::array($this->select(new Filter(groups: ['db'], excludeGroups: ['fast'])))
            ->hasCount(1)
            ->contains('dbTest')
            ->notContains('multiTest');
    }

    public function groupAndNameFilterCombineWithAnd(): void
    {
        # Name filter matches the whole class; group filter narrows it to `db`.
        Assert::same(
            $this->select(new Filter(names: ['GroupedTestClass'], groups: ['db'])),
            ['dbTest', 'multiTest'],
        );
    }

    public function nameFilterWithoutGroupMatchIsEmpty(): void
    {
        Assert::same($this->select(new Filter(names: ['nonExistentMethod'], groups: ['db'])), []);
    }

    public function leadingBackslashInFqnIsAccepted(): void
    {
        # A fully-qualified name written with a leading namespace separator must still match
        # the whole case (the interceptor trims surrounding `\` before matching).
        $fqn = '\\' . GroupedTestClass::class;

        Assert::same(
            $this->select(new Filter(names: [$fqn])),
            ['dbTest', 'multiTest', 'plainTest', 'slowTest'],
        );
    }

    public function includeGroupMatchingNothingYieldsEmpty(): void
    {
        Assert::same($this->select(new Filter(groups: ['nonExistentGroup'])), []);
    }

    public function excludeRemovingAllTestsOfACaseDropsIt(): void
    {
        # `integration` covers every method of GroupedTestClass, so the whole case is
        # dropped, leaving only the second case's tests.
        Assert::same(
            $this->select(new Filter(excludeGroups: ['integration'])),
            ['apiTest', 'ungrouped'],
        );
    }

    public function withoutAnyGroupFilterAllTestsRemain(): void
    {
        Assert::same(
            $this->select(new Filter()),
            ['apiTest', 'dbTest', 'multiTest', 'plainTest', 'slowTest', 'ungrouped'],
        );
    }

    /**
     * Run the locator chain (Testo attributes -> Filter) for the fixture and return the
     * sorted short names of the tests that survive filtering.
     *
     * @return list<string>
     */
    private function select(Filter $filter): array
    {
        $path = (new \ReflectionClass(GroupedTestClass::class))->getFileName();
        $file = new TokenizedFile(file: new \SplFileInfo($path), path: $path);
        $definition = new FileDefinitions(
            $file,
            classes: DefinitionLocator::getClasses($file),
            functions: DefinitionLocator::getFunctions($file),
        );

        # Stage 1: populate cases via the Testo attribute locator.
        (new TestoAttributesLocatorInterceptor())
            ->locateTestCases($definition, static fn(FileDefinitions $f): CaseDefinitions => $f->cases);

        # Stage 2: apply the filter.
        $cases = (new FilterInterceptor($filter))
            ->locateTestCases($definition, static fn(FileDefinitions $f): CaseDefinitions => $f->cases);

        $names = [];
        foreach ($cases->getCases() as $case) {
            foreach ($case->tests->getTests() as $name => $_) {
                $names[] = $name;
            }
        }

        \sort($names);

        return $names;
    }
}
