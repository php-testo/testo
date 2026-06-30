<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Reflection\ClassReflection;
use Rector\Rector\AbstractRector;
use Rector\Reflection\ReflectionResolver;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Flattens Testo's group *inheritance union* onto the concrete (leaf) PHPUnit test class.
 *
 * Testo computes a test's effective groups as the union over the method, its prototype (the
 * overridden parent/interface method), the test class, its parent classes and used traits — resolved
 * at run time (see {@see \Testo\Filter\Internal\FilterInterceptor} +
 * {@see \Testo\Common\Reflection::fetchFunctionAttributes()}/`fetchClassAttributes()`). PHPUnit does
 * not inherit `#[Group]` via reflection, so a faithful Testo → PHPUnit conversion must pull the
 * groups declared on ancestors down onto the leaf and emit them as PHPUnit's repeatable, single-name
 * `#[\PHPUnit\Framework\Attributes\Group('x')]` attributes.
 *
 * This rule is the complement of {@see GroupToPhpUnitRector}: that one rewrites a node's OWN variadic
 * `#[\Testo\Filter\Group('a','b')]` into repeated PHPUnit attributes, per-node, with no hierarchy
 * walk. This one walks the hierarchy and adds the inherited names the leaf does not already carry, at
 * two levels — the ancestor declarations themselves are never modified:
 *
 *  - Class level: the union of class-level groups over parent classes (recursively) and used traits
 *    (recursively) is flattened onto the leaf class.
 *  - Method level: for each method declared on the leaf, the same-named method on a parent class
 *    (recursively) contributes its method-level groups to the leaf method.
 *    This mirrors Testo's prototype walk (`\ReflectionMethod::getPrototype()`), which follows the
 *    parent-class / interface chain. It deliberately does NOT pull from traits: when a leaf method
 *    overrides a (same-named) trait method, PHP silently lets the class method win and `getPrototype()`
 *    is `false`, so Testo never sees the trait method's groups for that override either. A trait method
 *    the leaf does NOT override is simply the leaf's own method (its attribute is physically present and
 *    handled by {@see GroupToPhpUnitRector}); there is no method-level override-union from traits.
 *
 * Idempotency / coexistence: "already present on the leaf" is checked against BOTH a not-yet-converted
 * `#[\Testo\Filter\Group]` and an already-converted `#[\PHPUnit\Framework\Attributes\Group]`, so the
 * rule never duplicates a name regardless of whether {@see GroupToPhpUnitRector} has run yet (Rector
 * iterates the rule set to a fixed point).
 */
#[TestRectorFixtures('GroupInheritanceToPhpUnitRector')]
final class GroupInheritanceToPhpUnitRector extends AbstractRector
{
    private const TESTO_GROUP = 'Testo\\Filter\\Group';
    private const PHPUNIT_GROUP = 'PHPUnit\\Framework\\Attributes\\Group';

    public function __construct(
        private readonly ReflectionResolver $reflectionResolver,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Flatten Testo group inheritance (parent classes + traits) onto the concrete PHPUnit test class as repeated #[Group] attributes',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\Testo\Filter\Group('base')]
                        abstract class Base {}

                        #[\Testo\Filter\Group('leaf')]
                        final class FooTest extends Base {}
                        PHP,
                    <<<'PHP'
                        #[\Testo\Filter\Group('base')]
                        abstract class Base {}

                        #[\Testo\Filter\Group('leaf')]
                        #[\PHPUnit\Framework\Attributes\Group('base')]
                        final class FooTest extends Base {}
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
        # Only concrete leaf classes receive the flattened set; abstract classes are left as ancestors.
        if ($node->isAbstract() || $node->isAnonymous()) {
            return null;
        }

        # No parent class and no traits → nothing can be inherited; skip before touching reflection.
        if ($node->extends === null && $node->getTraitUses() === []) {
            return null;
        }

        $classReflection = $this->reflectionResolver->resolveClassReflection($node);
        if (!$classReflection instanceof ClassReflection || !$classReflection->isClass()) {
            return null;
        }

        $ancestors = $this->resolveAncestors($classReflection);
        if ($ancestors === []) {
            return null;
        }

        # Class-level and method-level inheritance are independent; OR their "did anything change" flags
        # so a class with only method-level inheritance (no class-level ancestor groups) still rewrites.
        $added = $this->applyClassLevel($node, $ancestors);
        $added = $this->applyMethodLevel($node, $classReflection) || $added;

        return $added ? $node : null;
    }

    /**
     * Flatten the class-level group inheritance union (ancestor classes + traits) onto the leaf class.
     *
     * @param list<ClassReflection> $ancestors
     *
     * @return bool Whether any attribute was appended to the class node.
     */
    private function applyClassLevel(Class_ $node, array $ancestors): bool
    {
        $inherited = [];
        foreach ($ancestors as $ancestor) {
            foreach ($this->groupNamesOf($ancestor->getNativeReflection()) as $name) {
                $inherited[$name] = true;
            }
        }
        if ($inherited === []) {
            return false;
        }

        $present = $this->groupNamesOnNode($node->attrGroups);

        $added = false;
        foreach (\array_keys($inherited) as $name) {
            if (isset($present[$name])) {
                continue;
            }

            $node->attrGroups[] = $this->phpunitGroupAttribute($name);
            $present[$name] = true;
            $added = true;
        }

        return $added;
    }

    /**
     * Inherit method-level groups along the prototype (parent-class) chain.
     *
     * For each method declared on the leaf, the same-named method on a parent class (recursively)
     * contributes its `#[\Testo\Filter\Group]` names to the leaf method. This mirrors Testo's
     * `\ReflectionMethod::getPrototype()` walk, which follows the parent-class/interface chain only —
     * traits are intentionally not consulted (see the class docblock).
     *
     * @return bool Whether any attribute was appended to any method node.
     */
    private function applyMethodLevel(Class_ $node, ClassReflection $classReflection): bool
    {
        $parents = $classReflection->getParents();
        if ($parents === []) {
            return false;
        }

        $added = false;
        foreach ($node->getMethods() as $method) {
            $methodName = $method->name->toString();

            $inherited = [];
            foreach ($parents as $parent) {
                $native = $parent->getNativeReflection();
                if (!$native->hasMethod($methodName)) {
                    continue;
                }

                $parentMethod = $native->getMethod($methodName);
                # Only a method actually declared on this ancestor contributes; an inherited-through
                # reference would double-count groups already collected from the declaring parent.
                if ($parentMethod->getDeclaringClass()->getName() !== $native->getName()) {
                    continue;
                }

                foreach ($this->groupNamesOf($parentMethod) as $name) {
                    $inherited[$name] = true;
                }
            }
            if ($inherited === []) {
                continue;
            }

            $present = $this->groupNamesOnNode($method->attrGroups);
            foreach (\array_keys($inherited) as $name) {
                if (isset($present[$name])) {
                    continue;
                }

                $method->attrGroups[] = $this->phpunitGroupAttribute($name);
                $present[$name] = true;
                $added = true;
            }
        }

        return $added;
    }

    /**
     * Build a single-name PHPUnit `#[\PHPUnit\Framework\Attributes\Group('x')]` attribute group.
     */
    private function phpunitGroupAttribute(string $name): AttributeGroup
    {
        return new AttributeGroup([
            new Attribute(
                new FullyQualified(self::PHPUNIT_GROUP),
                [new Arg(new String_($name))],
            ),
        ]);
    }

    /**
     * Parent classes (recursively) + used traits (recursively), excluding the leaf itself.
     *
     * @return list<ClassReflection>
     */
    private function resolveAncestors(ClassReflection $classReflection): array
    {
        $ancestors = [];

        foreach ($classReflection->getParents() as $parent) {
            $ancestors[$parent->getName()] = $parent;
        }
        foreach ($this->allTraits($classReflection) as $trait) {
            $ancestors[$trait->getName()] = $trait;
        }

        unset($ancestors[$classReflection->getName()]);

        return \array_values($ancestors);
    }

    /**
     * Traits used by the class and by every ancestor, recursively (traits can use traits).
     *
     * @return array<string, ClassReflection>
     */
    private function allTraits(ClassReflection $classReflection): array
    {
        $traits = [];
        $stack = [$classReflection, ...$classReflection->getParents()];

        while ($stack !== []) {
            $current = \array_pop($stack);
            foreach ($current->getTraits() as $trait) {
                if (isset($traits[$trait->getName()])) {
                    continue;
                }
                $traits[$trait->getName()] = $trait;
                $stack[] = $trait;
            }
        }

        return $traits;
    }

    /**
     * Names from `#[\Testo\Filter\Group]` attributes declared on the given (ancestor) class or method,
     * read from reflection without instantiating the attribute.
     *
     * @return list<string>
     */
    private function groupNamesOf(\ReflectionClass|\ReflectionMethod $reflection): array
    {
        $names = [];

        try {
            $attributes = $reflection->getAttributes();
        } catch (\Throwable) {
            return [];
        }

        foreach ($attributes as $attribute) {
            if (\ltrim($attribute->getName(), '\\') !== self::TESTO_GROUP) {
                continue;
            }

            try {
                $arguments = $attribute->getArguments();
            } catch (\Throwable) {
                continue;
            }

            foreach ($arguments as $argument) {
                \is_string($argument) && $argument !== '' and $names[] = $argument;
            }
        }

        return $names;
    }

    /**
     * Group names already written on a node (class or method), covering BOTH the not-yet-converted
     * Testo form and the already-converted PHPUnit form, so the rule is idempotent and order-independent.
     *
     * @param AttributeGroup[] $attrGroups
     *
     * @return array<string, true>
     */
    private function groupNamesOnNode(array $attrGroups): array
    {
        $present = [];

        foreach ($attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $isTesto = $this->isName($attr->name, self::TESTO_GROUP);
                $isPhpunit = $this->isName($attr->name, self::PHPUNIT_GROUP);
                if (!$isTesto && !$isPhpunit) {
                    continue;
                }

                foreach ($attr->args as $arg) {
                    if ($arg instanceof Arg && $arg->value instanceof String_) {
                        $present[$arg->value->value] = true;
                    }
                }
            }
        }

        return $present;
    }
}
