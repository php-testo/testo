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
 * Verifies the {@see \Testo\Filter\Group} filtering of {@see FilterInterceptor::locateTestCases()}.
 *
 * Fixture (two cases in one file). Effective group set = class-level groups + method-level groups:
 *
 *   GroupedTestClass        (class group: `integration`)
 *     - dbTest:    integration, db
 *     - slowTest:  integration, slow
 *     - plainTest: integration
 *     - multiTest: integration, db, fast
 *   OtherGroupedTestClass   (no class group)
 *     - apiTest:   api
 *     - ungrouped: (none)
 *
 * Filtering rules under test:
 *   - include groups  → OR  (a test passes if it is in ANY requested group)
 *   - exclude groups  → OR  (a test is dropped if it is in ANY excluded group)
 *   - exclude wins    → exclude is checked first and short-circuits, beating include
 *   - exclude-only    → everything not excluded runs, including tests with no groups
 *   - vs name filter  → AND (group filter narrows the name-matched set)
 */
#[Test]
#[Covers(FilterInterceptor::class)]
final class GroupFilterTest
{
    public function includeSingleGroupKeepsOnlyMatchingTests(): void
    {
        # include `db` → only tests whose group set contains `db` survive: dbTest and multiTest.
        Assert::array($this->select(new Filter(groups: ['db'])))
            ->hasCount(2)
            ->contains('dbTest')
            ->contains('multiTest')
            ->notContains('slowTest')
            ->notContains('plainTest');
    }

    public function includeMultipleGroupsUsesOrLogic(): void
    {
        # include `slow` OR `fast` → slowTest (slow) and multiTest (fast); a test needs to be
        # in just ONE of the requested groups, not all of them.
        Assert::array($this->select(new Filter(groups: ['slow', 'fast'])))
            ->hasCount(2)
            ->contains('slowTest')
            ->contains('multiTest')
            ->notContains('dbTest')
            ->notContains('plainTest');
    }

    /**
     * A class-level group is inherited by every method of that class, but must not leak into
     * other classes. `integration` lives only on GroupedTestClass, so exactly its four methods
     * survive and the second case (apiTest/ungrouped) is untouched.
     */
    public function classLevelGroupPropagatesToEveryTest(): void
    {
        Assert::same(
            $this->select(new Filter(groups: ['integration'])),
            ['dbTest', 'multiTest', 'plainTest', 'slowTest'],
        );
    }

    /**
     * When only the second case matches a group filter, the first (non-matching) case is skipped
     * and the interceptor keeps iterating instead of stopping. `api` lives only on the second case.
     */
    public function groupMatchingOnlyTheSecondCaseStillSurvives(): void
    {
        Assert::same($this->select(new Filter(groups: ['api'])), ['apiTest']);
    }

    /**
     * Same as above but driven by a name filter: the first case produces no match and is skipped,
     * yet the second case must still be reached and matched.
     */
    public function nameMatchingOnlyTheSecondCaseStillSurvives(): void
    {
        Assert::same($this->select(new Filter(names: ['apiTest'])), ['apiTest']);
    }

    public function excludeDropsMatchingTests(): void
    {
        # exclude `slow` → drop tests in group `slow`. Only slowTest has it, so the other five
        # (including the group-less `ungrouped`) survive.
        Assert::array($this->select(new Filter(excludeGroups: ['slow'])))
            ->hasCount(5)
            ->notContains('slowTest')
            ->contains('dbTest')
            ->contains('plainTest')
            ->contains('multiTest')
            ->contains('apiTest')
            ->contains('ungrouped');
    }

    public function multipleExcludeGroupsUseOrLogic(): void
    {
        # A test is dropped if it is in ANY of the excluded groups (`slow` OR `fast`).
        # slowTest (slow) and multiTest (fast) both go; everything else survives.
        Assert::array($this->select(new Filter(excludeGroups: ['slow', 'fast'])))
            ->hasCount(4)
            ->notContains('slowTest')
            ->notContains('multiTest')
            ->contains('dbTest')
            ->contains('plainTest')
            ->contains('apiTest')
            ->contains('ungrouped');
    }

    /**
     * Exclude beats include. With include `db` both dbTest and multiTest qualify, but exclude
     * `fast` also matches multiTest — and a test in both sets is dropped. Only dbTest survives.
     */
    public function excludeTakesPrecedenceOverInclude(): void
    {
        Assert::array($this->select(new Filter(groups: ['db'], excludeGroups: ['fast'])))
            ->hasCount(1)
            ->contains('dbTest')
            ->notContains('multiTest');
    }

    /**
     * The strongest form of "exclude wins": the same group is both included and excluded.
     * Exclude is checked first and short-circuits (include is never evaluated), so all `db`
     * tests are dropped and the result is empty.
     */
    public function sameGroupInIncludeAndExcludeIsExcluded(): void
    {
        Assert::same($this->select(new Filter(groups: ['db'], excludeGroups: ['db'])), []);
    }

    /**
     * Class-level include allowed, method-level exclude denied. The class matches the include
     * group (`integration`), so every test inherits the include match — yet a method carrying
     * the excluded group (`slow`) is still dropped. Verifies the per-method exclude is applied
     * even when the case as a whole is included.
     */
    public function classIncludedButMethodExcluded(): void
    {
        Assert::array($this->select(new Filter(groups: ['integration'], excludeGroups: ['slow'])))
            ->hasCount(3)
            ->notContains('slowTest')
            ->contains('dbTest')
            ->contains('plainTest')
            ->contains('multiTest');
    }

    /**
     * Class-level include allowed, exclude group absent everywhere. The class matches the include
     * group (`integration`), and the excluded group is carried by neither the class nor any method,
     * so the exclude filter removes nothing — every test of the case runs.
     */
    public function classIncludedWithExcludeGroupThatMatchesNothing(): void
    {
        Assert::same(
            $this->select(new Filter(groups: ['integration'], excludeGroups: ['nonexistent'])),
            ['dbTest', 'multiTest', 'plainTest', 'slowTest'],
        );
    }

    /**
     * With only an exclude filter and no include, every test that is not excluded runs —
     * including tests that carry no groups at all (here: ungrouped).
     */
    public function ungroupedTestSurvivesExcludeOnlyFilter(): void
    {
        Assert::array($this->select(new Filter(excludeGroups: ['db'])))
            ->contains('ungrouped')
            ->contains('plainTest')
            ->notContains('dbTest')
            ->notContains('multiTest');
    }

    /**
     * Name and group filters combine with AND: the name filter selects the whole GroupedTestClass
     * case (4 tests), then the group filter narrows that set to `db` → dbTest and multiTest.
     */
    public function groupAndNameFilterCombineWithAnd(): void
    {
        Assert::same(
            $this->select(new Filter(names: ['GroupedTestClass'], groups: ['db'])),
            ['dbTest', 'multiTest'],
        );
    }

    /**
     * AND again: the name filter matches nothing, so even a satisfiable group filter cannot
     * bring tests back — the intersection is empty.
     */
    public function nameFilterWithoutGroupMatchIsEmpty(): void
    {
        Assert::same($this->select(new Filter(names: ['nonExistentMethod'], groups: ['db'])), []);
    }

    /**
     * A fully-qualified name written with a leading namespace separator must still match the
     * whole case; the interceptor trims surrounding `\` before matching.
     */
    public function leadingBackslashInFqnIsAccepted(): void
    {
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

    /**
     * When an exclude filter removes every test of a case, the case itself disappears from the
     * result. `integration` covers all of GroupedTestClass, leaving only the second case.
     */
    public function excludeRemovingAllTestsOfACaseDropsIt(): void
    {
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
