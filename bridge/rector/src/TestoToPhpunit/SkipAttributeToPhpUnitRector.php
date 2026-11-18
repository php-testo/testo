<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
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
use PhpParser\Node\Stmt\If_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Internal\DroppedAttributes;
use Testo\Bridge\Rector\Internal\OriginalTokens;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Converts Testo's `#[\Testo\Skip]` attribute into a `$this->markTestSkipped($reason)` call at the
 * top of each test method it applies to, and each `#[\Testo\Requires]` into the same call guarded
 * by `if (!condition()) { … }`, dropping the attributes.
 *
 * PHPUnit has no declarative skip attribute, so the nearest faithful form is the call PHPUnit's own
 * `markTestSkipped()` makes: the test is reported as skipped and its body never runs. The reason is
 * forwarded as the message; a reason-less `#[Skip]` becomes a bare `$this->markTestSkipped()`
 * (Testo's generated `{testId} is skipped via #[Skip]` message has no PHPUnit counterpart).
 *
 * Testo allows the attribute on a **class** too; there it is fanned out onto each test method
 * (mirroring how Testo skips every test of the case) and removed from the class. A method carrying
 * its own `#[Skip]` keeps its own reason — method-level wins over class-level as a whole, reason
 * included. Requirements accumulate instead: the class-level guards come first, then the method's
 * own, and a `#[Skip]` that applies replaces them all, since Testo checks none of them for a skipped
 * test. Test methods are found the same way as {@see RepeatRetryRector}: the `#[Test]`-marked
 * methods (Testo's or the already-converted PHPUnit form), or — under a class-level
 * `#[\Testo\Test]` — every public, non-static, `void`/`never`, non-lifecycle method.
 *
 * **Residual:** the skip becomes a runtime one. Testo keeps a `#[Skip]`-ed test out of the per-test
 * pipeline entirely (no `#[BeforeTest]`, no data provider call, no retries); PHPUnit runs `setUp()`
 * and the data provider, then aborts inside the test body. A `#[Skip]` on a free function or on a
 * non-test member is left untouched — `$this->markTestSkipped()` needs a test method to live in,
 * and on a non-test member the attribute is inert in Testo anyway.
 */
#[TestRectorFixtures('SkipAttributeToPhpUnitRector')]
final class SkipAttributeToPhpUnitRector extends AbstractRector
{
    use OriginalTokens;

    private const SKIP_TESTO = 'Testo\\Skip';
    private const REQUIRES_TESTO = 'Testo\\Requires';

    /** @var list<non-empty-string> */
    private const LIFECYCLE_ATTRIBUTES = [
        'Testo\\Lifecycle\\BeforeTest',
        'Testo\\Lifecycle\\AfterTest',
        'Testo\\Lifecycle\\BeforeClass',
        'Testo\\Lifecycle\\AfterClass',
        'PHPUnit\\Framework\\Attributes\\Before',
        'PHPUnit\\Framework\\Attributes\\After',
        'PHPUnit\\Framework\\Attributes\\BeforeClass',
        'PHPUnit\\Framework\\Attributes\\AfterClass',
    ];

    /** @var list<string> */
    private const LIFECYCLE_NAMES = ['setup', 'teardown', 'setupbeforeclass', 'teardownafterclass'];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert the Testo #[Skip] attribute into a leading $this->markTestSkipped($reason) call and #[Requires] into the same call guarded by its negated condition, fanning class-level attributes out onto each test method',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\Testo\Skip('broken by the pricing rework')]
                        public function test(): void
                        {
                            $this->doSomething();
                        }
                        PHP,
                    <<<'PHP'
                        public function test(): void
                        {
                            $this->markTestSkipped('broken by the pricing rework');
                            $this->doSomething();
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
        $targets = $this->testMethods($node);
        if ($targets === []) {
            # Nothing that carries a skip here (not a test class); leave every attribute untouched.
            return null;
        }

        if (!$this->convertible($node, $targets)) {
            return null;
        }

        $classSkip = $this->classAttributes($node, self::SKIP_TESTO)[0] ?? null;
        $classRequires = $this->classAttributes($node, self::REQUIRES_TESTO);
        $changed = false;

        foreach ($targets as $method) {
            if ($method->stmts === null) {
                continue;
            }

            $skip = $this->takeMethodAttributes($method, self::SKIP_TESTO)[0] ?? $classSkip;
            $requires = [...$classRequires, ...$this->takeMethodAttributes($method, self::REQUIRES_TESTO)];

            # A skip wins without any requirement being checked, as in Testo.
            $guards = $skip !== null
                ? [$this->skipCall($skip, 0)]
                : \array_map(
                    fn(Attribute $requirement): If_ => new If_(
                        new BooleanNot($this->conditionCall($requirement, $node)),
                        ['stmts' => [$this->skipCall($requirement, 1)]],
                    ),
                    $requires,
                );
            if ($guards === []) {
                continue;
            }

            \array_unshift($method->stmts, ...$guards);
            $changed = true;
        }

        $classSkip === null or $this->removeClassAttribute($node, self::SKIP_TESTO);
        $classRequires === [] or $this->removeClassAttribute($node, self::REQUIRES_TESTO);

        return $changed || $classSkip !== null || $classRequires !== [] ? $node : null;
    }

    /**
     * `self`, `static` and `parent` stay relative: a leading backslash would not compile.
     */
    private static function className(string $name): Name
    {
        return \in_array(\strtolower($name), ['self', 'static', 'parent'], true)
            ? new Name($name)
            : new FullyQualified(\ltrim($name, '\\'));
    }

    /**
     * Whether every `#[Requires]` the class would lose names its condition. A misspelled argument
     * name leaves it without one, and the whole class stays untouched: a half-converted class
     * would mix both frameworks' skips.
     *
     * @param list<ClassMethod> $targets
     */
    private function convertible(Class_ $class, array $targets): bool
    {
        foreach ([$class, ...$targets] as $holder) {
            foreach ($holder->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    if ($this->isName($attr->name, self::REQUIRES_TESTO)
                        && $this->argument($attr, 0, 'condition') === null
                    ) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Removing the attributes here is what keeps the emitted calls the only trace of the skip.
     *
     * @return list<Attribute>
     */
    private function takeMethodAttributes(ClassMethod $method, string $name): array
    {
        $found = [];
        $kept = [];
        foreach ($method->attrGroups as $attrGroup) {
            $attrGroup->attrs = \array_values(\array_filter(
                $attrGroup->attrs,
                function (Attribute $attr) use ($name, &$found): bool {
                    if (!$this->isName($attr->name, $name)) {
                        return true;
                    }

                    $found[] = $attr;
                    return false;
                },
            ));
            $attrGroup->attrs === [] or $kept[] = $attrGroup;
        }

        if ($found !== []) {
            $method->attrGroups = $kept;
            DroppedAttributes::keepFormat($method, $this->originalTokens());
        }

        return $found;
    }

    /**
     * @return list<Attribute>
     */
    private function classAttributes(Class_ $class, string $name): array
    {
        $found = [];
        foreach ($class->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $this->isName($attr->name, $name) and $found[] = $attr;
            }
        }

        return $found;
    }

    private function removeClassAttribute(Class_ $class, string $name): void
    {
        $kept = [];
        foreach ($class->attrGroups as $attrGroup) {
            $attrGroup->attrs = \array_values(\array_filter(
                $attrGroup->attrs,
                fn(Attribute $attr): bool => !$this->isName($attr->name, $name),
            ));
            $attrGroup->attrs === [] or $kept[] = $attrGroup;
        }

        $class->attrGroups = $kept;
        DroppedAttributes::keepFormat($class, $this->originalTokens());
    }

    /**
     * `$this->markTestSkipped()` with the attribute's reason as the sole positional message (the
     * named `reason:` form loses its name — PHPUnit's parameter is `$message`); a reason-less
     * attribute yields a bare call.
     *
     * @param int<0, max> $reasonPosition
     */
    private function skipCall(Attribute $attribute, int $reasonPosition): Expression
    {
        $reason = $this->argument($attribute, $reasonPosition, 'reason');

        return new Expression(new MethodCall(
            new Variable('this'),
            new Identifier('markTestSkipped'),
            $reason === null ? [] : [new Arg($this->copy($reason))],
        ));
    }

    /**
     * The call of a `#[Requires]` condition. Literal callables become direct calls — the guard sits
     * in the test class, so a private `[self::class, 'helper']` stays reachable — and anything else
     * is called as it stands. A bare name resolves as Testo resolves it, see {@see hasStaticMethod()}.
     */
    private function conditionCall(Attribute $requires, Class_ $class): Expr
    {
        $condition = $this->copy($this->argument($requires, 0, 'condition') ?? throw new \LogicException(
            'refactor() leaves a #[Requires] without a condition unconverted.',
        ));

        if ($condition instanceof String_) {
            $parts = \explode('::', $condition->value, 2);
            if (\count($parts) === 2) {
                return new StaticCall(self::className($parts[0]), $parts[1]);
            }

            return $this->hasStaticMethod($class, $parts[0])
                ? new StaticCall(new Name('self'), $parts[0])
                : new FuncCall(new FullyQualified(\ltrim($parts[0], '\\')));
        }

        if ($condition instanceof Array_
            && \count($condition->items) === 2
            && $condition->items[1]?->value instanceof String_
        ) {
            $owner = $condition->items[0]?->value;
            $method = $condition->items[1]->value->value;

            if ($owner instanceof ClassConstFetch && $this->isName($owner->name, 'class')) {
                return new StaticCall($owner->class, $method);
            }

            if ($owner instanceof String_) {
                return new StaticCall(self::className($owner->value), $method);
            }
        }

        if ($condition instanceof CallLike && $condition->isFirstClassCallable()) {
            $condition->args = [];
            return $condition;
        }

        return new FuncCall($condition);
    }

    /**
     * Mirrors Testo's resolution of a bare name: a static method of the test class, declared or
     * inherited, wins over a function. A namespaced name is never a method.
     */
    private function hasStaticMethod(Class_ $class, string $name): bool
    {
        if (\str_contains($name, '\\')) {
            return false;
        }

        $declared = $class->getMethod($name);
        if ($declared !== null) {
            return $declared->isStatic();
        }

        $className = $class->namespacedName?->toString();
        if ($className === null || !$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        $reflection = $this->reflectionProvider->getClass($className);

        return $reflection->hasNativeMethod($name) && $reflection->getNativeMethod($name)->isStatic();
    }

    /**
     * An attribute argument by position or name, as it stands in the attribute.
     *
     * @param int<0, max> $position
     */
    private function argument(Attribute $attribute, int $position, string $name): ?Expr
    {
        foreach ($attribute->args as $index => $arg) {
            if ($arg instanceof Arg && ($arg->name === null ? $index === $position : $arg->name->toString() === $name)) {
                return $arg->value;
            }
        }

        return null;
    }

    /**
     * A deep copy, so a class-level attribute fanned out onto several methods shares no node
     * between them, closure bodies included.
     */
    private function copy(Expr $expr): Expr
    {
        /** @var Expr */
        return (new NodeTraverser(new CloningVisitor()))->traverse([$expr])[0];
    }

    /**
     * The methods a skip can apply to, mirroring Testo discovery.
     *
     * @return list<ClassMethod>
     */
    private function testMethods(Class_ $class): array
    {
        $marked = [];
        foreach ($class->getMethods() as $method) {
            if ($this->methodHasKind($method, 'Testo\\Test', 'PHPUnit\\Framework\\Attributes\\Test')) {
                $marked[] = $method;
            }
        }
        if ($marked !== []) {
            return $marked;
        }

        if ($this->classAttributes($class, 'Testo\\Test') === []) {
            return [];
        }

        $discovered = [];
        foreach ($class->getMethods() as $method) {
            $this->isDiscoverableByClassLevelTest($method) and $discovered[] = $method;
        }

        return $discovered;
    }

    /**
     * Mirrors Testo's locator for a class-level `#[Test]`.
     */
    private function isDiscoverableByClassLevelTest(ClassMethod $method): bool
    {
        if (!$method->isPublic() || $method->isStatic() || $this->isLifecycleMethod($method)) {
            return false;
        }

        $returnType = $method->returnType;

        return $returnType instanceof Identifier && \in_array($returnType->toLowerString(), ['void', 'never'], true);
    }

    private function isLifecycleMethod(ClassMethod $method): bool
    {
        if (\in_array(\strtolower((string) $this->getName($method)), self::LIFECYCLE_NAMES, true)) {
            return true;
        }

        return $this->methodHasKind($method, ...self::LIFECYCLE_ATTRIBUTES);
    }

    private function methodHasKind(ClassMethod $method, string ...$names): bool
    {
        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                foreach ($names as $name) {
                    if ($this->isName($attr->name, $name)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
