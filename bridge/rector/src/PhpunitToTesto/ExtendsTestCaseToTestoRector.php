<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeVisitor;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Internal\DroppedAttributes;
use Testo\Bridge\Rector\Internal\OriginalTokens;
use Testo\Bridge\Rector\Internal\PhpDocTagText;
use Testo\Bridge\Rector\Internal\PhpunitAssertionCall;
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
 *     interface cannot be resolved;
 *   - rewrites the `parent::` call statements that resolve into PHPUnit, since PHP rejects `parent`
 *     in a class without one: a `parent::setUp()`-style hook call, or a call to the PHPUnit method
 *     its own enclosing method overrides, is dropped, and any other call, such as
 *     `parent::markTestSkipped()`, becomes `self::`/`$this->` for the skip and expectation rules to
 *     convert. A call whose result is used is left for the manual pass. An anonymous class that
 *     extends `TestCase` directly is handled the same way; one on a project base is left untouched.
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
 * subclass's tests at all. Its `parent::` calls to a PHPUnit method the base does not override are
 * rewritten the same way. A base that cannot be resolved, or does not lead to `TestCase`, leaves the
 * class untouched.
 *
 * A base from outside the processed paths, such as a framework's test case in vendor, stays a
 * PHPUnit class. Its subclasses are converted all the same, keep their `parent::` calls, and the
 * class extending that base gets `#[\Testo\Skip]` with a reason naming it, until the base is
 * rewritten for Testo.
 *
 * A trait gets `#[\Testo\Test]` on the same test methods, since the classes that use it are out of
 * sight: a test method a PHPUnit class takes from a trait would otherwise go undiscovered. Abstract
 * methods are skipped; the class implementing one marks it. An abstract method that only restates a
 * PHPUnit assertion (an `assert*`/`fail` declared on PHPUnit's `Assert`, untyped or returning
 * `void`/`never`) is removed: its calls convert to Testo, and a using class that no longer extends
 * `TestCase` would be left with an unimplemented method.
 *
 * Residual: methods are NOT renamed. Keeping `testFoo()` is harmless under Testo (discovery
 * is by attribute, not name), but call-site rewriting / cleanup of the `test` prefix is out
 * of scope and left for manual follow-up.
 */
#[TestRectorFixtures('ExtendsTestCaseToTestoRector')]
final class ExtendsTestCaseToTestoRector extends AbstractRector
{
    use OriginalTokens;

    /**
     * `TestCase` methods whose `parent::` call only reaches PHPUnit's own plumbing; Testo runs the
     * overriding method on its own, so the call has nothing left to do.
     */
    private const DROPPED_HOOKS = [
        '__construct',
        'setup',
        'teardown',
        'setupbeforeclass',
        'teardownafterclass',
        'assertpreconditions',
        'assertpostconditions',
        'onnotsuccessfultest',
    ];

    public function __construct(
        private readonly PhpDocInfoFactory $phpDocInfoFactory,
        private readonly PhpDocTagRemover $phpDocTagRemover,
        private readonly DocBlockUpdater $docBlockUpdater,
        private readonly ReflectionProvider $reflectionProvider,
        private readonly PhpunitTestCaseClass $testCaseClass,
        private readonly PhpunitAssertionCall $assertionCall,
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

            $kept = \array_values(\array_filter(
                $node->stmts,
                fn(Node $stmt): bool => !$stmt instanceof ClassMethod || !$this->assertionCall->restatesPhpunitAssertion($node, $stmt),
            ));
            if (\count($kept) !== \count($node->stmts)) {
                $node->stmts = $kept;
                $changed = true;
            }

            return $changed ? $node : null;
        }

        if (!$this->testCaseClass->extendsDirectly($node)) {
            if (!$this->testCaseClass->isTestCase($node)) {
                return null;
            }

            # Indirect subclass: the base stays, only discovery needs the attribute.
            $vendorBase = $this->testCaseClass->phpunitBaseFromVendor($node);
            $base = $vendorBase === null ? $this->base($node) : null;
            $changed = false;
            foreach ($node->getMethods() as $method) {
                $method->isPublic() && $this->markTestMethod($method) and $changed = true;
                $base !== null && $this->rewritePhpunitParentCalls($base, $method) and $changed = true;
            }

            $vendorBase !== null && $vendorBase['direct'] && $this->skipOnVendorBase($node, $vendorBase['name'])
                and $changed = true;

            return $changed ? $node : null;
        }

        $base = $this->base($node);
        $node->extends = null;

        $interfaces = $this->nodeNameResolver->getNames($node->implements);
        foreach ($node->getMethods() as $method) {
            $method->isPublic() and $this->markTestMethod($method);
            $this->isDeclaredByInterface($method, $interfaces) or $this->removeOverrideAttribute($method);
            $this->rewritePhpunitParentCalls($base, $method);
        }

        return $node;
    }

    /**
     * Marks the class that extends a PHPUnit base from vendor with `#[\Testo\Skip]`: Testo would run
     * its tests with that base still built for PHPUnit. A class-level skip is inherited, so marking the
     * topmost local class covers its subclasses, and a skipped case is neither instantiated nor given
     * its hooks. A class that already carries a skip keeps it.
     *
     * @return bool Whether the attribute was added.
     */
    private function skipOnVendorBase(Class_ $class, string $vendorBase): bool
    {
        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($this->isName($attr->name, 'Testo\\Skip')) {
                    return false;
                }
            }
        }

        $group = new AttributeGroup([
            new Attribute(new FullyQualified('Testo\\Skip'), [new Arg(new String_(\sprintf(
                'Extends %s, a PHPUnit base from vendor: rewrite the base for Testo, then remove this attribute',
                $vendorBase,
            )))]),
        ]);
        # A position-less group would pull the class's start line to 0 once it leads the list.
        $group->setAttribute('startLine', $class->getStartLine());
        $class->attrGroups[] = $group;

        return true;
    }

    /**
     * The class's direct base as loaded from source, read from the name it extends before its
     * `extends` is removed: an anonymous class has no name of its own to reflect.
     *
     * @return ClassReflection|null Null when the base cannot be resolved.
     */
    private function base(Class_ $class): ?ClassReflection
    {
        $base = $class->extends === null ? null : $this->getName($class->extends);

        return $base !== null && $this->reflectionProvider->hasClass($base)
            ? $this->reflectionProvider->getClass($base)
            : null;
    }

    /**
     * Rewrites the `parent::method()` statements that resolve into PHPUnit, which the converted chain
     * no longer extends: without a parent PHP rejects `parent` at compile time, and on a converted base
     * the method is gone. A call reaching a method a local base declares stays, as does a call whose
     * result is used. An anonymous class inside the method has a parent of its own and is not entered.
     *
     * Only a call to a `TestCase` hook, or to the PHPUnit method the enclosing one overrides, is
     * dropped. A PHPUnit assertion becomes an explicit `\PHPUnit\Framework\Assert::` call: the
     * assertion rules convert the ones Testo maps, and the rest stay valid PHPUnit calls. Any other
     * call, such as `markTestSkipped()` or `expectException()`, becomes `self::` or `$this->`, the
     * spelling the skip and expectation rules convert by name; dropping it would silently change what
     * the test checks.
     *
     * Without the base's reflection, as for a class extending `TestCase` directly while PHPUnit cannot
     * be resolved, every call is taken to reach PHPUnit.
     *
     * @return bool Whether a call was changed.
     */
    private function rewritePhpunitParentCalls(?ClassReflection $base, ClassMethod $method): bool
    {
        $native = $base?->getNativeReflection();
        $changed = false;
        # Within its own override a rewritten call would reach the override again.
        $dropped = [...self::DROPPED_HOOKS, $method->name->toLowerString()];
        $inStatic = $method->isStatic();
        $this->traverseNodesWithCallable($method, function (Node $node) use ($native, $dropped, $inStatic, &$changed): ?int {
            if ($node instanceof Class_) {
                return NodeVisitor::DONT_TRAVERSE_CHILDREN;
            }

            if (!$node instanceof Expression
                || !$node->expr instanceof StaticCall
                || !$node->expr->class instanceof Name
                || $node->expr->class->toLowerString() !== 'parent'
                || !$node->expr->name instanceof Identifier
            ) {
                return null;
            }

            $called = $node->expr->name->toString();
            # The base's reflection covers every inherited method. A method no ancestor declares
            # becomes `$this->` too: a visible call to a missing method rather than a statement that
            # silently disappears.
            $static = false;
            if ($native?->hasMethod($called)) {
                $declared = $native->getMethod($called);
                if (!\str_starts_with($declared->getDeclaringClass()->getName(), 'PHPUnit\\')) {
                    return null;
                }

                $static = $declared->isStatic();
            }

            $changed = true;
            if ($this->isPhpunitAssertion($node->expr)) {
                $node->expr->class = new FullyQualified('PHPUnit\\Framework\\Assert');

                return null;
            }

            if (\in_array(\strtolower($called), $dropped, true)) {
                return NodeVisitor::REMOVE_NODE;
            }

            # A static method has no `$this` to call on.
            if ($static || $inStatic) {
                $node->expr->class = new Name('self');
            } else {
                $node->expr = new MethodCall(new Variable('this'), $node->expr->name, $node->expr->args);
            }

            return null;
        });

        return $changed;
    }

    /**
     * Only a static assertion declared on `Assert` counts; `TestCase` hooks such as
     * `assertPostConditions()` exist on the instance alone and are dropped as hooks.
     */
    private function isPhpunitAssertion(StaticCall $call): bool
    {
        $name = \strtolower((string) $this->assertionCall->name($call));

        return (\str_starts_with($name, 'assert') || $name === 'fail')
            && $this->reflectionProvider->hasClass('PHPUnit\\Framework\\Assert')
            && $this->reflectionProvider->getClass('PHPUnit\\Framework\\Assert')->hasNativeMethod($name);
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
        DroppedAttributes::keepFormat($method, $this->originalTokens());
    }
}
