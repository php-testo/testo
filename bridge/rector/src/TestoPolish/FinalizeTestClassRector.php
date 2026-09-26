<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoPolish;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Internal\ProcessedClasses;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Makes a Testo test class `final`: a class carrying `#[\Testo\Test]` on itself or on a method.
 *
 * Testo's counterpart of Rector's `FinalizeTestCaseClassRector`, which only recognises PHPUnit's
 * `TestCase`. The same naming guard applies: only a class named `*Test` is finalized, and a
 * `*TestCase` class is left alone, since both conventions mark a class meant to be extended.
 * Abstract, anonymous and already final classes are skipped, as is a class that another class in
 * the processed paths extends. A subclass outside those paths is not seen.
 */
#[TestRectorFixtures('FinalizeTestClassRector')]
final class FinalizeTestClassRector extends AbstractRector
{
    public function __construct(
        private readonly ProcessedClasses $processedClasses,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Make a Testo test class final',
            [
                new CodeSample(
                    <<<'PHP'
                        class OrderTest
                        {
                            #[\Testo\Test]
                            public function createsOrder(): void {}
                        }
                        PHP,
                    <<<'PHP'
                        final class OrderTest
                        {
                            #[\Testo\Test]
                            public function createsOrder(): void {}
                        }
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if ($node->isAbstract() || $node->isAnonymous() || $node->isFinal()) {
            return null;
        }

        $name = (string) $this->getName($node);
        if (!\str_ends_with($name, 'Test') || \str_ends_with($name, 'TestCase')) {
            return null;
        }

        if (!$this->isTestClass($node) || $this->processedClasses->hasSubclass($name)) {
            return null;
        }

        $node->flags |= Modifiers::FINAL;

        return $node;
    }

    private function isTestClass(Class_ $class): bool
    {
        if ($this->hasTestAttribute($class->attrGroups)) {
            return true;
        }

        foreach ($class->getMethods() as $method) {
            if ($this->hasTestAttribute($method->attrGroups)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<AttributeGroup> $groups
     */
    private function hasTestAttribute(array $groups): bool
    {
        foreach ($groups as $group) {
            foreach ($group->attrs as $attr) {
                if ($this->isName($attr->name, 'Testo\\Test')) {
                    return true;
                }
            }
        }

        return false;
    }
}
