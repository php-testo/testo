<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoPolish;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Trait_;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Internal\ProcessedClasses;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Moves `#[\Testo\Test]` from the methods of a test class onto the class, when that selects exactly
 * the same tests.
 *
 * On a class, `#[Test]` makes a test of every public method returning `void` or `never` — own,
 * inherited or taken from a trait — while the lifecycle plugin takes its hooks back out. The move
 * therefore needs all of:
 *   - the class is `final` and not abstract: the class attribute is inherited, and a subclass the
 *     rule cannot see would turn its public `void` helpers into tests;
 *   - every public `void`/`never` method is a test or carries a lifecycle attribute
 *     (`#[BeforeTest]`, `#[AfterTest]`, `#[BeforeClass]`, `#[AfterClass]`), and so is every public
 *     method without a return type, which the return-type rules of the same set may turn `void`;
 *   - every `#[Test]` method is public and returns `void` or `never`, or it would stop being a test;
 *   - at least one method is a test.
 *
 * Methods inherited or taken from a trait are read from PHPStan's reflection of the original
 * source, so an attribute another rule adds to them in the same run is not seen yet and the class
 * is left for the next run. A class that already carries `#[Test]` only loses the method attributes
 * it makes redundant; a test outside the class-level selection keeps its own.
 *
 * A trait loses the `#[Test]` of its public `void`/`never` methods once every class that uses it
 * within the processed paths selects tests by a class-level `#[Test]`. A trait without users, or with
 * one that is not class-level, renames its methods (`insteadof`, `as`) or is an enum, keeps them.
 */
#[TestRectorFixtures('ClassLevelTestAttributeRector')]
final class ClassLevelTestAttributeRector extends AbstractRector
{
    private const TEST = 'Testo\\Test';

    /** Attributes whose methods the lifecycle plugin excludes from the class-level selection. */
    private const LIFECYCLE = [
        'Testo\\Lifecycle\\BeforeTest',
        'Testo\\Lifecycle\\AfterTest',
        'Testo\\Lifecycle\\BeforeClass',
        'Testo\\Lifecycle\\AfterClass',
    ];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly ProcessedClasses $processedClasses,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Move #[\Testo\Test] from the methods onto the class when every public void/never method is a test',
            [
                new CodeSample(
                    <<<'PHP'
                        final class OrderTest
                        {
                            #[\Testo\Test]
                            public function createsOrder(): void {}

                            #[\Testo\Test]
                            public function cancelsOrder(): void {}
                        }
                        PHP,
                    <<<'PHP'
                        #[\Testo\Test]
                        final class OrderTest
                        {
                            public function createsOrder(): void {}

                            public function cancelsOrder(): void {}
                        }
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Class_::class, Trait_::class];
    }

    /**
     * @param Class_|Trait_ $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Trait_) {
            return $this->refactorTrait($node);
        }

        if ($node->isAnonymous()) {
            return null;
        }

        $tests = [];
        foreach ($node->getMethods() as $method) {
            $this->hasAttribute($method->attrGroups, [self::TEST]) and $tests[] = $method;
        }

        if ($tests === []) {
            return null;
        }

        $selected = \array_values(\array_filter(
            $tests,
            fn(ClassMethod $method): bool => $method->isPublic() && $this->returnsVoidOrNever($method),
        ));

        if ($this->hasAttribute($node->attrGroups, [self::TEST])) {
            # The class attribute already selects these; a method outside the selection keeps its own.
            if ($selected === []) {
                return null;
            }

            $this->removeTestAttribute($selected);

            return $node;
        }

        if (!$node->isFinal() || $node->isAbstract() || \count($selected) !== \count($tests)) {
            return null;
        }

        foreach ($node->getMethods() as $method) {
            if ($method->isPublic()
                && ($this->returnsVoidOrNever($method) || $this->mayBecomeVoid($method))
                && !$this->hasAttribute($method->attrGroups, [self::TEST, ...self::LIFECYCLE])
            ) {
                return null;
            }
        }

        if (!$this->foreignMethodsAreTests($node)) {
            return null;
        }

        $this->removeTestAttribute($tests);

        $group = new AttributeGroup([new Attribute(new FullyQualified(self::TEST))]);
        # A position-less group would pull the class's start line to 0 once it leads the list.
        $group->setAttribute('startLine', $node->getStartLine());
        $node->attrGroups[] = $group;

        return $node;
    }

    /**
     * Drops the `#[Test]` attributes of a trait that the class-level attribute of every user already
     * makes redundant. The users are read from the processed files as they are on disk, so a user
     * that gains its class attribute in this run is seen on the next one.
     */
    private function refactorTrait(Trait_ $trait): ?Trait_
    {
        $name = $trait->namespacedName?->toString();
        if ($name === null) {
            return null;
        }

        $selected = [];
        foreach ($trait->getMethods() as $method) {
            $method->isPublic()
                && $this->returnsVoidOrNever($method)
                && $this->hasAttribute($method->attrGroups, [self::TEST])
                and $selected[] = $method;
        }

        if ($selected === [] || !$this->processedClasses->isUsedOnlyByClassLevelTests($name)) {
            return null;
        }

        $this->removeTestAttribute($selected);

        return $trait;
    }

    /**
     * Whether every public `void`/`never` method the class inherits or takes from a trait is a test
     * or a lifecycle hook, as far as the reflection of the original source tells.
     */
    private function foreignMethodsAreTests(Class_ $class): bool
    {
        $hasTraits = \array_filter($class->stmts, static fn(Node $stmt): bool => $stmt instanceof TraitUse) !== [];
        if ($class->extends === null && !$hasTraits) {
            return true;
        }

        $name = $class->namespacedName?->toString();
        if ($name === null || !$this->reflectionProvider->hasClass($name)) {
            return false;
        }

        $own = [];
        foreach ($class->getMethods() as $method) {
            $own[\strtolower($method->name->toString())] = true;
        }

        foreach ($this->reflectionProvider->getClass($name)->getNativeReflection()->getMethods() as $method) {
            if (isset($own[\strtolower($method->getName())]) || !$method->isPublic()) {
                continue;
            }

            $returnType = $method->getReturnType();
            $untyped = $returnType === null && !\str_starts_with($method->getName(), '__');
            if (!$untyped && !\in_array((string) $returnType, ['void', 'never'], true)) {
                continue;
            }

            $attributes = \array_map(static fn(object $attr): string => $attr->getName(), $method->getAttributes());
            if (\array_intersect($attributes, [self::TEST, ...self::LIFECYCLE]) === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * A public method without a return type may gain `void` or `never` later in the same run, which
     * would pull it into the class-level selection. Magic methods never get one.
     */
    private function mayBecomeVoid(ClassMethod $method): bool
    {
        return $method->returnType === null && !\str_starts_with($method->name->toString(), '__');
    }

    private function returnsVoidOrNever(ClassMethod $method): bool
    {
        return $method->returnType instanceof Identifier
            && \in_array($method->returnType->toLowerString(), ['void', 'never'], true);
    }

    /**
     * @param array<AttributeGroup> $groups
     * @param list<non-empty-string> $names
     */
    private function hasAttribute(array $groups, array $names): bool
    {
        foreach ($groups as $group) {
            foreach ($group->attrs as $attr) {
                if ($this->isNames($attr->name, $names)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<ClassMethod> $methods
     */
    private function removeTestAttribute(array $methods): void
    {
        foreach ($methods as $method) {
            # Trim groups in place: a rebuilt group would lose its source position.
            $kept = [];
            foreach ($method->attrGroups as $group) {
                $group->attrs = \array_values(\array_filter(
                    $group->attrs,
                    fn(Attribute $attr): bool => !$this->isName($attr->name, self::TEST),
                ));
                $group->attrs === [] or $kept[] = $group;
            }

            $method->attrGroups = $kept;
        }
    }
}
