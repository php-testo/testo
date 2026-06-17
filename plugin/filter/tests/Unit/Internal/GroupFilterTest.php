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
        Assert::same(
            $this->select(new Filter(groups: ['integration'])),
            ['dbTest', 'multiTest', 'plainTest', 'slowTest'],
        );
    }

    public function excludeDropsMatchingTests(): void
    {
        Assert::array($this->select(new Filter(excludeGroups: ['slow'])))
            ->hasCount(3)
            ->notContains('slowTest')
            ->contains('dbTest')
            ->contains('plainTest')
            ->contains('multiTest');
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

    public function withoutAnyGroupFilterAllTestsRemain(): void
    {
        Assert::same(
            $this->select(new Filter()),
            ['dbTest', 'multiTest', 'plainTest', 'slowTest'],
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
