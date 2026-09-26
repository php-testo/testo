<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Trait_;
use PHPStan\Reflection\ReflectionProvider;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Internal\PhpDocTagText;
use Testo\Bridge\Rector\Internal\PhpunitTestCaseClass;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Detaches a PHPUnit test class from its base class and makes it discoverable by Testo.
 *
 * For a class that **directly** `extends \PHPUnit\Framework\TestCase` (whether written
 * fully-qualified, as a bare imported `TestCase`, or aliased), this rule:
 *   - removes the `extends` clause,
 *   - marks discovery as attribute-based by adding `#[\Testo\Test]` to every test method, and
 *   - drops `#[\Override]` from methods that no longer override anything (`setUp()` and other
 *     `TestCase` hooks), since PHP rejects the attribute on a method without a parent declaration.
 *     A method declared by one of the class's interfaces keeps it, as does every method when an
 *     interface cannot be resolved.
 *
 * "Test method" mirrors PHPUnit's own discovery: a method carrying the PHPUnit
 * `#[\PHPUnit\Framework\Attributes\Test]` attribute, a method with a `@test` docblock
 * annotation, or a method whose name starts with `test`. The PHPUnit `#[Test]` attribute
 * is rewritten to `#[\Testo\Test]`; a `@test` annotation is removed and replaced by the
 * attribute; a bare `test`-prefixed method simply gains the attribute. The rule is
 * idempotent — a method that already carries `#[\Testo\Test]` is left as-is.
 *
 * A class that reaches `TestCase` through an intermediate base (`extends RuleTestCase`) keeps its
 * `extends` and its `#[\Override]` attributes — the base is still there, converted on its own —
 * and only gains `#[\Testo\Test]` on its test methods. Without them Testo would not discover the
 * subclass's tests at all. A base that cannot be resolved, or does not lead to `TestCase`, leaves
 * the class untouched.
 *
 * A trait gets `#[\Testo\Test]` on the same test methods, since the classes that use it are out of
 * sight: a test method a PHPUnit class takes from a trait would otherwise go undiscovered. Abstract
 * methods are skipped; the class implementing one marks it.
 *
 * Residual: methods are NOT renamed. Keeping `testFoo()` is harmless under Testo (discovery
 * is by attribute, not name), but call-site rewriting / cleanup of the `test` prefix is out
 * of scope and left for manual follow-up.
 */
#[TestRectorFixtures('ExtendsTestCaseToTestoRector')]
final class ExtendsTestCaseToTestoRector extends AbstractRector
{
    public function __construct(
        private readonly PhpDocInfoFactory $phpDocInfoFactory,
        private readonly PhpDocTagRemover $phpDocTagRemover,
        private readonly DocBlockUpdater $docBlockUpdater,
        private readonly ReflectionProvider $reflectionProvider,
        private readonly PhpunitTestCaseClass $testCaseClass,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove "extends PHPUnit\\Framework\\TestCase" and make the class attribute-discoverable by adding #[\\Testo\\Test] to its test methods',
            [
                new CodeSample(
                    <<<'PHP'
                        final class MyTest extends \PHPUnit\Framework\TestCase
                        {
                            public function testFoo(): void {}
                        }
                        PHP,
                    <<<'PHP'
                        final class MyTest
                        {
                            #[\Testo\Test]
                            public function testFoo(): void {}
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
            # A trait cannot tell which classes use it; its test methods are marked on their own.
            $changed = false;
            foreach ($node->getMethods() as $method) {
                $method->isPublic() && !$method->isAbstract() && $this->markTestMethod($method) and $changed = true;
            }

            return $changed ? $node : null;
        }

        if (!$this->testCaseClass->extendsDirectly($node)) {
            if (!$this->testCaseClass->isTestCase($node)) {
                return null;
            }

            # Indirect subclass: the base stays, only discovery needs the attribute.
            $changed = false;
            foreach ($node->getMethods() as $method) {
                $method->isPublic() && $this->markTestMethod($method) and $changed = true;
            }

            return $changed ? $node : null;
        }

        $node->extends = null;

        $interfaces = $this->nodeNameResolver->getNames($node->implements);
        foreach ($node->getMethods() as $method) {
            $method->isPublic() and $this->markTestMethod($method);
            $this->isDeclaredByInterface($method, $interfaces) or $this->removeOverrideAttribute($method);
        }

        return $node;
    }

    /**
     * @return bool Whether the method was changed.
     */
    private function markTestMethod(ClassMethod $method): bool
    {
        # Idempotent: a method already carrying #[\Testo\Test] needs nothing.
        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isName($attr->name, 'Testo\\Test')) {
                    return false;
                }
            }
        }

        # PHPUnit #[Test] attribute -> rewrite in place to #[\Testo\Test].
        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isName($attr->name, 'PHPUnit\\Framework\\Attributes\\Test')) {
                    $attr->name = new FullyQualified('Testo\\Test');

                    return true;
                }
            }
        }

        # @test annotation -> drop the tag and add the attribute.
        $phpDocInfo = $this->phpDocInfoFactory->createFromNode($method);
        if ($phpDocInfo instanceof PhpDocInfo) {
            $testTags = $phpDocInfo->getTagsByName('test');
            if ($testTags !== []) {
                foreach ($testTags as $tag) {
                    PhpDocTagText::of($tag) === null or $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $tag);
                }
                $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($method);
                $this->addTestoAttribute($method);

                return true;
            }
        }

        # `test`-prefixed name with no explicit marker -> add the attribute.
        $name = $this->getName($method->name);
        if ($name !== null && \str_starts_with($name, 'test')) {
            $this->addTestoAttribute($method);

            return true;
        }

        return false;
    }

    private function addTestoAttribute(ClassMethod $method): void
    {
        $method->attrGroups[] = new AttributeGroup([
            new Attribute(new FullyQualified('Testo\\Test')),
        ]);
    }

    /**
     * @param list<string> $interfaces
     */
    private function isDeclaredByInterface(ClassMethod $method, array $interfaces): bool
    {
        $name = $this->getName($method->name);
        foreach ($interfaces as $interface) {
            if (!$this->reflectionProvider->hasClass($interface)) {
                return true;
            }

            if ($name !== null && $this->reflectionProvider->getClass($interface)->hasMethod($name)) {
                return true;
            }
        }

        return false;
    }

    private function removeOverrideAttribute(ClassMethod $method): void
    {
        # Trim groups in place: a rebuilt group would lose its source position.
        $keptGroups = [];
        foreach ($method->attrGroups as $attrGroup) {
            $attrGroup->attrs = \array_values(\array_filter(
                $attrGroup->attrs,
                fn(Attribute $attr): bool => !$this->isName($attr->name, 'Override'),
            ));
            $attrGroup->attrs === [] or $keptGroups[] = $attrGroup;
        }

        $method->attrGroups = $keptGroups;
    }
}
