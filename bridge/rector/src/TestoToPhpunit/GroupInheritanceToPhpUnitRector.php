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
use PHPStan\Reflection\ReflectionProvider;
use Rector\Configuration\Option;
use Rector\Configuration\Parameter\SimpleParameterProvider;
use Rector\NodeTypeResolver\Reflection\BetterReflection\SourceLocatorProvider\DynamicSourceLocatorProvider;
use Rector\Rector\AbstractRector;
use Rector\Reflection\ReflectionResolver;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Flattens Testo's group *inheritance union* onto the concrete (leaf) PHPUnit test class.
 *
 * Testo computes a test's effective groups as the union over the method, the test class, its
 * parent classes and used traits — resolved at run time. PHPUnit does not inherit class-level
 * `#[Group]` from parents via reflection, so a faithful Testo → PHPUnit conversion must pull the
 * groups declared on ancestors down onto the leaf and emit them as PHPUnit's repeatable,
 * single-name `#[\PHPUnit\Framework\Attributes\Group('x')]` attributes.
 *
 * This rule is the complement of {@see GroupToPhpUnitRector}: that one rewrites a node's OWN
 * variadic `#[\Testo\Filter\Group('a','b')]` into repeated PHPUnit attributes, per-node, with no
 * hierarchy walk. This one walks ancestors (parent classes recursively + used traits, recursively)
 * and adds the inherited names that the leaf does not already carry. The ancestor classes themselves
 * are never modified.
 *
 * Idempotency / coexistence: "already present on the leaf" is checked against BOTH a not-yet-converted
 * `#[\Testo\Filter\Group]` and an already-converted `#[\PHPUnit\Framework\Attributes\Group]`, so the
 * rule never duplicates a name regardless of whether {@see GroupToPhpUnitRector} has run yet (Rector
 * iterates the rule set to a fixed point).
 *
 * @todo Method-level override-union (a leaf method inheriting groups from the same method on a
 *   parent/trait) is not implemented; only class-level hierarchy is flattened. See TODO.md.
 */
#[TestRectorFixtures('GroupInheritanceToPhpUnitRector')]
final class GroupInheritanceToPhpUnitRector extends AbstractRector
{
    private const TESTO_GROUP = 'Testo\\Filter\\Group';
    private const PHPUNIT_GROUP = 'PHPUnit\\Framework\\Attributes\\Group';

    public function __construct(
        private readonly ReflectionResolver $reflectionResolver,
        private readonly ReflectionProvider $reflectionProvider,
        private readonly DynamicSourceLocatorProvider $sourceLocatorProvider,
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

        $classReflection = $this->resolveLeafReflection($node);
        if (!$classReflection instanceof ClassReflection || !$classReflection->isClass()) {
            return null;
        }

        $ancestors = $this->resolveAncestors($classReflection);
        if ($ancestors === []) {
            return null;
        }

        $inherited = [];
        foreach ($ancestors as $ancestor) {
            foreach ($this->groupNamesOf($ancestor) as $name) {
                $inherited[$name] = true;
            }
        }
        if ($inherited === []) {
            return null;
        }

        $present = $this->groupNamesOnLeaf($node);

        $added = false;
        foreach (\array_keys($inherited) as $name) {
            if (isset($present[$name])) {
                continue;
            }

            $node->attrGroups[] = new AttributeGroup([
                new Attribute(
                    new FullyQualified(self::PHPUNIT_GROUP),
                    [new Arg(new String_($name))],
                ),
            ]);
            $present[$name] = true;
            $added = true;
        }

        return $added ? $node : null;
    }

    /**
     * Resolve the leaf class reflection, robustly across a single shared Rector container.
     *
     * We re-point the BetterReflection source locator at the file currently being processed before
     * resolving by name. This is necessary because the locator caches an aggregate built from the
     * first file it sees (it only rebuilds per file under PHPUnit), so when several files share one
     * container — as the bridge's fixture harness does — a later file's classes would otherwise be
     * invisible and ancestors would resolve to nothing.
     *
     * To keep a real multi-file Rector run correct, we restore the original `--source` paths on the
     * locator afterwards, so the temporary re-point is invisible to the rules that run after us. We
     * deliberately avoid the node's pre-computed scope here: reading it would resolve the class
     * against the stale locator and poison BetterReflection's per-name cache for the rest of the run.
     */
    private function resolveLeafReflection(Class_ $node): ?ClassReflection
    {
        $name = $this->getName($node);
        if ($name === null) {
            return null;
        }

        $filePath = $this->file->getFilePath();
        if ($filePath === '') {
            # No file context (should not happen during a normal run); fall back to the scope.
            $byScope = $this->reflectionResolver->resolveClassReflection($node);

            return $byScope instanceof ClassReflection ? $byScope : null;
        }

        $this->sourceLocatorProvider->reset();
        $this->sourceLocatorProvider->setFilePath($filePath);

        try {
            return $this->reflectionProvider->hasClass($name)
                ? $this->reflectionProvider->getClass($name)
                : null;
        } finally {
            $this->restoreSourceLocator($filePath);
        }
    }

    /**
     * Put the locator back to the run's configured `--source` paths, so re-pointing it at a single
     * file does not strip cross-file reflection from the rules (or files) processed after this one.
     */
    private function restoreSourceLocator(string $currentFile): void
    {
        $source = SimpleParameterProvider::provideArrayParameter(Option::SOURCE);
        if ($source === [$currentFile] || $source === []) {
            # The run only ever targeted this file; nothing broader to restore.
            return;
        }

        $this->sourceLocatorProvider->reset();
        $files = \array_values(\array_filter($source, '\is_file'));
        $directories = \array_values(\array_filter($source, '\is_dir'));
        $files === [] || $this->sourceLocatorProvider->addFiles($files);
        $directories === [] || $this->sourceLocatorProvider->addDirectories($directories);
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
     * Names from `#[\Testo\Filter\Group]` attributes declared on the given (ancestor) class,
     * read from reflection without instantiating the attribute.
     *
     * @return list<string>
     */
    private function groupNamesOf(ClassReflection $classReflection): array
    {
        $names = [];

        try {
            $attributes = $classReflection->getNativeReflection()->getAttributes();
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
     * Group names already written on the leaf node, covering BOTH the not-yet-converted Testo form
     * and the already-converted PHPUnit form, so the rule is idempotent and order-independent.
     *
     * @return array<string, true>
     */
    private function groupNamesOnLeaf(Class_ $node): array
    {
        $present = [];

        foreach ($node->attrGroups as $attrGroup) {
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
