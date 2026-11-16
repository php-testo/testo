<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeVisitor;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Internal\PhpunitAssertionCall;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Marks a custom assertion helper with `#[\Testo\Common\Attribute\AssertMethod]`, so a failure inside
 * it is reported from the helper's frame instead of the assertion library's internals.
 *
 * A helper is an `assert*` method or function whose own body asserts: it calls a PHPUnit assertion
 * (`$this->assertX()`, `self::assertX()`, `parent::assertX()`, `Assert::assertX()`, `fail()`, the
 * `PHPUnit\Framework\assertX()` functions), `\Testo\Assert`, or another helper of the same class.
 * Calls inside a closure or a nested class run in another frame and do not count.
 *
 * Testo does not run a method that carries the attribute, so a method it would run as a test is
 * never marked: one with `#[Test]` or `@test`, and a public `void`/`never` method of a class whose
 * own or inherited `#[\Testo\Test]` makes every such method a test. A trait cannot tell whether a
 * class-level `#[Test]` reaches it, so its public `void`/`never` methods are left alone as well.
 *
 * PHPUnit needs no such marker, since it filters its own frames from the trace by itself.
 *
 * @api
 */
#[TestRectorFixtures('AssertMethodAttributeRector')]
final class AssertMethodAttributeRector extends AbstractRector
{
    private const ATTRIBUTE = 'Testo\\Common\\Attribute\\AssertMethod';
    private const TESTO_TEST = 'Testo\\Test';
    private const TEST_ATTRIBUTES = [self::TESTO_TEST, 'PHPUnit\\Framework\\Attributes\\Test'];
    private const FUNCTION_NAMESPACE = 'PHPUnit\\Framework\\';

    public function __construct(
        private readonly PhpunitAssertionCall $assertionCall,
        private readonly PhpDocInfoFactory $phpDocInfoFactory,
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Mark custom assert* helpers that call assertions with #[\Testo\Common\Attribute\AssertMethod]',
            [
                new CodeSample(
                    <<<'PHP'
                        protected function assertValidUser(User $user): void
                        {
                            $this->assertNotEmpty($user->name);
                        }
                        PHP,
                    <<<'PHP'
                        #[\Testo\Common\Attribute\AssertMethod]
                        protected function assertValidUser(User $user): void
                        {
                            $this->assertNotEmpty($user->name);
                        }
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [ClassLike::class, Function_::class];
    }

    /**
     * @param ClassLike|Function_ $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Function_) {
            return $this->isCandidate($node) && $this->asserts($node, []) && $this->mark($node) ? $node : null;
        }

        $helpers = [];
        $candidates = [];
        $publicTests = null;
        foreach ($node->getMethods() as $method) {
            if ($this->hasAttribute($method, [self::ATTRIBUTE])) {
                $helpers[$method->name->toLowerString()] = true;
                continue;
            }

            if (!$this->isCandidate($method)) {
                continue;
            }

            # Reflection is walked only once a method could be taken for a test.
            if ($this->runsAsTest($method)) {
                $publicTests ??= $node instanceof Trait_ || $this->hasClassLevelTest($node);
                if ($publicTests) {
                    continue;
                }
            }

            $candidates[$method->name->toLowerString()] = $method;
        }

        # A helper may assert only through another helper of the same class, so the set grows until
        # a pass adds nothing.
        $marked = [];
        do {
            $found = false;
            foreach ($candidates as $name => $method) {
                if ($this->asserts($method, $helpers)) {
                    $helpers[$name] = true;
                    $marked[] = $method;
                    unset($candidates[$name]);
                    $found = true;
                }
            }
        } while ($found);

        foreach ($marked as $method) {
            $this->mark($method);
        }

        return $marked === [] ? null : $node;
    }

    private function isCandidate(ClassMethod|Function_ $function): bool
    {
        return $function->stmts !== null
            && \str_starts_with($function->name->toLowerString(), 'assert')
            && !$this->hasAttribute($function, [self::ATTRIBUTE, ...self::TEST_ATTRIBUTES])
            && $this->phpDocInfoFactory->createFromNode($function)?->hasByName('test') !== true;
    }

    /**
     * Mirrors Testo's class-level discovery: a public method returning `void` or `never`.
     */
    private function runsAsTest(ClassMethod $method): bool
    {
        return $method->isPublic()
            && $method->returnType instanceof Identifier
            && \in_array($method->returnType->toLowerString(), ['void', 'never'], true);
    }

    /**
     * Testo reads the class-level `#[Test]` off the class, its parents and their traits.
     */
    private function hasClassLevelTest(ClassLike $class): bool
    {
        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($this->isName($attr->name, self::TESTO_TEST)) {
                    return true;
                }
            }
        }

        $name = $class->namespacedName?->toString();
        if ($name === null || !$this->reflectionProvider->hasClass($name)) {
            return false;
        }

        $reflection = $this->reflectionProvider->getClass($name);
        foreach ([$reflection, ...$reflection->getParents()] as $level) {
            foreach ([$level, ...\array_values($level->getTraits(true))] as $declaring) {
                if ($declaring->getNativeReflection()->getAttributes(self::TESTO_TEST) !== []) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<lowercase-string, true> $helpers Helpers of the same class found so far.
     */
    private function asserts(ClassMethod|Function_ $function, array $helpers): bool
    {
        $asserts = false;
        $this->traverseNodesWithCallable((array) $function->stmts, function (Node $node) use ($helpers, &$asserts): ?int {
            if ($node instanceof FunctionLike || $node instanceof ClassLike) {
                return NodeVisitor::DONT_TRAVERSE_CHILDREN;
            }

            if ($node instanceof CallLike && $this->isAssertion($node, $helpers)) {
                $asserts = true;

                return NodeVisitor::STOP_TRAVERSAL;
            }

            return null;
        });

        return $asserts;
    }

    /**
     * @param array<lowercase-string, true> $helpers
     */
    private function isAssertion(CallLike $call, array $helpers): bool
    {
        if ($call instanceof StaticCall && $this->isName($call->class, 'Testo\\Assert')) {
            return true;
        }

        if ($call instanceof FuncCall) {
            return $this->isAssertionName($this->phpunitFunctionName($call));
        }

        if (!$call instanceof MethodCall && !$call instanceof StaticCall) {
            return false;
        }

        $name = $this->getName($call->name);
        if ($name === null || !$this->isAssertionName($name)) {
            return false;
        }

        $ownCall = $call instanceof MethodCall
            ? $this->isName($call->var, 'this')
            : $this->isNames($call->class, ['self', 'static']);
        if ($ownCall && isset($helpers[\strtolower($name)])) {
            return true;
        }

        return $this->assertionCall->name($call) !== null
            || $call instanceof StaticCall && $this->isName($call->class, 'parent') && $this->parentHelper($call, $name);
    }

    /**
     * A PHPUnit assertion reached through `parent::` is recognised above; this covers a helper of the
     * parent that already carries the attribute.
     */
    private function parentHelper(StaticCall $call, string $name): bool
    {
        $scope = $call->getAttribute(AttributeKey::SCOPE);
        $parent = $scope instanceof Scope ? $scope->getClassReflection()?->getParentClass() : null;
        if ($parent === null || !$parent->hasNativeMethod($name)) {
            return false;
        }

        $declaring = $parent->getNativeMethod($name)->getDeclaringClass();

        return $declaring->getNativeReflection()->getMethod($name)->getAttributes(self::ATTRIBUTE) !== [];
    }

    private function isAssertionName(?string $name): bool
    {
        return $name !== null && (\str_starts_with(\strtolower($name), 'assert') || \strtolower($name) === 'fail');
    }

    /**
     * Read off the name alone: outside a class {@see PhpunitAssertionCall} does not recognise the
     * functions, and a free helper function is where they are called from.
     */
    private function phpunitFunctionName(FuncCall $call): ?string
    {
        $name = \ltrim((string) $this->getName($call), '\\');

        return \str_starts_with($name, self::FUNCTION_NAMESPACE)
            ? \substr($name, \strlen(self::FUNCTION_NAMESPACE))
            : null;
    }

    private function mark(ClassMethod|Function_ $function): bool
    {
        $attribute = new Attribute(new FullyQualified(self::ATTRIBUTE));
        $last = $function->attrGroups === [] ? null : $function->attrGroups[\array_key_last($function->attrGroups)];
        if ($last !== null && $last->getEndLine() === $function->name->getStartLine()) {
            # A new group after an inline one is printed on the signature line, not on its own.
            $last->attrs[] = $attribute;

            return true;
        }

        $group = new AttributeGroup([$attribute]);
        # A position-less group would pull the function's start line to 0 once it leads the list.
        $group->setAttribute('startLine', $function->getStartLine());
        $function->attrGroups[] = $group;

        return true;
    }

    /**
     * @param list<string> $names
     */
    private function hasAttribute(ClassMethod|Function_ $function, array $names): bool
    {
        foreach ($function->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($this->isNames($attr->name, $names)) {
                    return true;
                }
            }
        }

        return false;
    }
}
