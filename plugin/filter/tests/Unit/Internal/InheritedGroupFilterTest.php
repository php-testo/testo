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
use Tests\Filter\Unit\Fixture\InheritedGroupTest;

/**
 * Verifies that {@see \Testo\Filter\Group} is merged from every layer the interceptor traverses:
 * the method, its prototype (overridden parent method), the test class, its parent class, and traits.
 *
 * Fixture {@see InheritedGroupTest} (extends a `#[Group('base')]` parent, uses a trait, is `#[Group('child')]`):
 *   - inherited(): own, proto, child, base
 *   - fromTrait():  trait-group, child, base
 *   - childOnly():  child, base
 */
#[Test]
#[Covers(FilterInterceptor::class)]
final class InheritedGroupFilterTest
{
    /**
     * A parent class group is inherited by every test of the subclass.
     */
    public function parentClassGroupAppliesToAllTests(): void
    {
        Assert::same($this->select(['base']), ['childOnly', 'fromTrait', 'inherited']);
    }

    /**
     * The subclass's own class-level group also covers every test.
     */
    public function childClassGroupAppliesToAllTests(): void
    {
        Assert::same($this->select(['child']), ['childOnly', 'fromTrait', 'inherited']);
    }

    /**
     * A group declared on the overridden parent method (the prototype) is inherited by the override.
     */
    public function prototypeMethodGroupIsInherited(): void
    {
        Assert::same($this->select(['proto']), ['inherited']);
    }

    /**
     * A group declared on the overriding method itself is honored.
     */
    public function ownMethodGroupIsApplied(): void
    {
        Assert::same($this->select(['own']), ['inherited']);
    }

    /**
     * A group declared on a trait method applies to the method the trait provides.
     */
    public function traitMethodGroupIsApplied(): void
    {
        Assert::same($this->select(['trait-group']), ['fromTrait']);
    }

    /**
     * Run the locator chain for the inheritance fixture and return the sorted names of the
     * tests that survive the given include-group filter.
     *
     * @param list<non-empty-string> $groups
     * @return list<string>
     */
    private function select(array $groups): array
    {
        $path = (new \ReflectionClass(InheritedGroupTest::class))->getFileName();
        $file = new TokenizedFile(file: new \SplFileInfo($path), path: $path);
        $definition = new FileDefinitions(
            $file,
            classes: DefinitionLocator::getClasses($file),
            functions: DefinitionLocator::getFunctions($file),
        );

        (new TestoAttributesLocatorInterceptor())
            ->locateTestCases($definition, static fn(FileDefinitions $f): CaseDefinitions => $f->cases);

        $cases = (new FilterInterceptor(new Filter(groups: $groups)))
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
