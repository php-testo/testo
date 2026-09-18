<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Converts Testo's `#[\Testo\Skip]` attribute into a `$this->markTestSkipped($reason)` call at the
 * top of each test method it applies to, and drops the attribute.
 *
 * PHPUnit has no declarative skip attribute, so the nearest faithful form is the call PHPUnit's own
 * `markTestSkipped()` makes: the test is reported as skipped and its body never runs. The reason is
 * forwarded as the message; a reason-less `#[Skip]` becomes a bare `$this->markTestSkipped()`
 * (Testo's generated `{testId} is skipped via #[Skip]` message has no PHPUnit counterpart).
 *
 * Testo allows the attribute on a **class** too; there it is fanned out onto each test method
 * (mirroring how Testo skips every test of the case) and removed from the class. A method carrying
 * its own `#[Skip]` keeps its own reason — method-level wins over class-level as a whole, reason
 * included. Test methods are found the same way as {@see RepeatRetryRector}: the `#[Test]`-marked
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
    private const SKIP_TESTO = 'Testo\\Skip';

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

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert the Testo #[Skip] attribute into a leading $this->markTestSkipped($reason) call, fanning a class-level attribute out onto each test method',
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

        $classSkip = $this->classAttribute($node, self::SKIP_TESTO);
        $changed = false;

        foreach ($targets as $method) {
            $skip = $this->takeMethodAttribute($method, self::SKIP_TESTO) ?? $classSkip;
            if ($skip === null || $method->stmts === null) {
                continue;
            }

            \array_unshift($method->stmts, new Expression(
                new MethodCall(new Variable('this'), new Identifier('markTestSkipped'), $this->messageArgs($skip)),
            ));
            $changed = true;
        }

        if ($classSkip !== null) {
            $this->removeClassAttribute($node, self::SKIP_TESTO);
            $changed = true;
        }

        return $changed ? $node : null;
    }

    /**
     * Removing the attribute here is what keeps the emitted call the only trace of the skip.
     */
    private function takeMethodAttribute(ClassMethod $method, string $name): ?Attribute
    {
        $found = null;
        $kept = [];
        foreach ($method->attrGroups as $attrGroup) {
            $attrGroup->attrs = \array_values(\array_filter(
                $attrGroup->attrs,
                function (Attribute $attr) use ($name, &$found): bool {
                    if (!$this->isName($attr->name, $name)) {
                        return true;
                    }

                    $found ??= $attr;
                    return false;
                },
            ));
            $attrGroup->attrs === [] or $kept[] = $attrGroup;
        }

        $found === null or $method->attrGroups = $kept;

        return $found;
    }

    private function classAttribute(Class_ $class, string $name): ?Attribute
    {
        foreach ($class->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isName($attr->name, $name)) {
                    return $attr;
                }
            }
        }

        return null;
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
    }

    /**
     * `markTestSkipped()` arguments from a `#[Skip]`: the reason as the sole positional message
     * (the named `reason:` form loses its name — PHPUnit's parameter is `$message`), cloned so a
     * class-level attribute can be fanned out onto several methods with independent nodes. A
     * reason-less attribute yields no arguments.
     *
     * @return list<Arg>
     */
    private function messageArgs(Attribute $skip): array
    {
        foreach ($skip->args as $position => $arg) {
            if (!$arg instanceof Arg) {
                continue;
            }

            if ($arg->name === null ? $position === 0 : $arg->name->toString() === 'reason') {
                return [new Arg(clone $arg->value)];
            }
        }

        return [];
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

        if ($this->classAttribute($class, 'Testo\\Test') === null) {
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
