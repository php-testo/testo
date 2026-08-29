<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Converts Testo's `#[\Testo\Repeat]` / `#[\Testo\Retry]` attributes into PHPUnit's `#[Repeat]` /
 * `#[Retry]` (available since PHPUnit 13.3):
 *
 *   - #[\Testo\Repeat(times: N)]                 → #[\PHPUnit\Framework\Attributes\Repeat(N)]
 *   - #[\Testo\Repeat(times: N, maxFailures: M)] → #[\PHPUnit\Framework\Attributes\Repeat(N, M + 1)]
 *   - #[\Testo\Retry(maxAttempts: N)]           → #[\PHPUnit\Framework\Attributes\Retry(N)]
 *
 * Testo's `maxFailures` (tolerated failures, default 0) maps to PHPUnit's `failureThreshold`
 * (aborting failure count, default 1) as `failureThreshold = maxFailures + 1`; the Testo default 0
 * folds back to PHPUnit's default 1 and is omitted. PHPUnit's attributes are positional
 * (`@no-named-arguments`), so the emitted arguments carry no names. Testo's defaults are made
 * explicit where PHPUnit has no matching default drop (`times` → `2`, `maxAttempts` → `3`).
 *
 * Testo allows the attribute on a **class** too; PHPUnit's `Repeat`/`Retry` are `TARGET_METHOD`
 * only, so a class-level attribute is **fanned out onto each test method** (mirroring how Testo
 * applies it to every test in the class) and removed from the class. A method that carries its own
 * `#[Repeat]`/`#[Retry]` keeps it — a method-level attribute overrides the class-level default and is
 * not doubled. Test methods are found the same way as {@see TestClassToTestCaseRector}: the
 * `#[Test]`-marked methods (Testo's or the already-converted PHPUnit form), or — under a class-level
 * `#[\Testo\Test]` — every public, non-static, `void`/`never`, non-lifecycle method.
 *
 * **Residual:** Testo's `markFlaky` flag is dropped — PHPUnit has no flaky-marking equivalent.
 */
#[TestRectorFixtures('RepeatRetryRector')]
final class RepeatRetryRector extends AbstractRector
{
    private const REPEAT_TESTO = 'Testo\\Repeat';
    private const RETRY_TESTO = 'Testo\\Retry';
    private const REPEAT_PHPUNIT = 'PHPUnit\\Framework\\Attributes\\Repeat';
    private const RETRY_PHPUNIT = 'PHPUnit\\Framework\\Attributes\\Retry';

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
            'Convert Testo #[Repeat]/#[Retry] attributes into PHPUnit #[Repeat]/#[Retry], fanning a class-level attribute out onto each test method',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\Testo\Repeat(times: 5, maxFailures: 1)]
                        public function test(): void {}
                        PHP,
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\Repeat(5, 2)]
                        public function test(): void {}
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
        # 1. Method-level attributes: convert each in place.
        $changed = false;
        foreach ($node->getMethods() as $method) {
            foreach ($method->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    if ($this->isName($attr->name, self::REPEAT_TESTO)) {
                        $attr->args = $this->repeatArgs($attr);
                        $attr->name = new FullyQualified(self::REPEAT_PHPUNIT);
                        $changed = true;
                    } elseif ($this->isName($attr->name, self::RETRY_TESTO)) {
                        $attr->args = $this->retryArgs($attr);
                        $attr->name = new FullyQualified(self::RETRY_PHPUNIT);
                        $changed = true;
                    }
                }
            }
        }

        # 2. Class-level attributes: PHPUnit has no class target, so fan out onto each test method.
        return $this->fanOutClassLevel($node) || $changed ? $node : null;
    }

    /**
     * Push a class-level `#[\Testo\Repeat]`/`#[\Testo\Retry]` down onto each test method as its PHPUnit
     * form and drop it from the class. A method already carrying its own attribute of the same kind is
     * left alone (method-level overrides the class-level default). Returns whether anything changed.
     */
    private function fanOutClassLevel(Class_ $class): bool
    {
        $repeat = $this->classAttribute($class, self::REPEAT_TESTO);
        $retry = $this->classAttribute($class, self::RETRY_TESTO);
        if ($repeat === null && $retry === null) {
            return false;
        }

        $targets = $this->testMethods($class);
        if ($targets === []) {
            # Nothing to carry the attribute (not a test class here); leave it untouched.
            return false;
        }

        foreach ($targets as $method) {
            if ($repeat !== null && !$this->methodHasKind($method, self::REPEAT_TESTO, self::REPEAT_PHPUNIT)) {
                $method->attrGroups[] = new AttributeGroup([
                    new Attribute(new FullyQualified(self::REPEAT_PHPUNIT), $this->repeatArgs($repeat)),
                ]);
            }
            if ($retry !== null && !$this->methodHasKind($method, self::RETRY_TESTO, self::RETRY_PHPUNIT)) {
                $method->attrGroups[] = new AttributeGroup([
                    new Attribute(new FullyQualified(self::RETRY_PHPUNIT), $this->retryArgs($retry)),
                ]);
            }
        }

        $this->removeClassAttributes($class, [self::REPEAT_TESTO, self::RETRY_TESTO]);

        return true;
    }

    /** The first class-level attribute with the given name, or null. */
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

    /**
     * @param list<string> $names
     */
    private function removeClassAttributes(Class_ $class, array $names): void
    {
        $kept = [];
        foreach ($class->attrGroups as $attrGroup) {
            $attrGroup->attrs = \array_values(\array_filter(
                $attrGroup->attrs,
                fn(Attribute $attr): bool => !$this->isAnyName($attr, $names),
            ));
            $attrGroup->attrs === [] or $kept[] = $attrGroup;
        }

        $class->attrGroups = $kept;
    }

    /**
     * The methods a class-level attribute should fan out to, mirroring Testo discovery: the
     * `#[Test]`-marked methods when any exist (Testo's or the converted PHPUnit form), otherwise —
     * under a class-level `#[\Testo\Test]` — every public, non-static, void/never, non-lifecycle method.
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

        if (!$this->hasClassLevelTest($class)) {
            return [];
        }

        $discovered = [];
        foreach ($class->getMethods() as $method) {
            $this->isDiscoverableByClassLevelTest($method) and $discovered[] = $method;
        }

        return $discovered;
    }

    private function hasClassLevelTest(Class_ $class): bool
    {
        return $this->classAttribute($class, 'Testo\\Test') !== null;
    }

    /**
     * Mirrors Testo's locator: a public, non-static method with a `void`/`never` return type that is
     * not a lifecycle hook.
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

    /** Whether the method carries any attribute named among $names. */
    private function methodHasKind(ClassMethod $method, string ...$names): bool
    {
        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isAnyName($attr, $names)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<string> $names
     */
    private function isAnyName(Attribute $attr, array $names): bool
    {
        foreach ($names as $name) {
            if ($this->isName($attr->name, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * PHPUnit `Repeat` positional args from a Testo `#[Repeat]`: `times` (default 2) and, unless it
     * collapses to the default, `failureThreshold = maxFailures + 1`. Values are cloned so the same
     * source attribute can be fanned out onto several methods with independent nodes.
     *
     * @return list<Arg>
     */
    private function repeatArgs(Attribute $src): array
    {
        $times = $this->argValue($src->args, 'times', 0);
        $args = [new Arg($times !== null ? clone $times : new Int_(2))];

        $maxFailures = $this->argValue($src->args, 'maxFailures', 1);
        if ($maxFailures !== null && ($threshold = $this->maxFailuresToThreshold(clone $maxFailures)) !== null) {
            $args[] = new Arg($threshold);
        }

        return $args;
    }

    /**
     * PHPUnit `Retry` positional args from a Testo `#[Retry]`: `maxAttempts` (default 3), cloned.
     *
     * @return list<Arg>
     */
    private function retryArgs(Attribute $src): array
    {
        $maxAttempts = $this->argValue($src->args, 'maxAttempts', 0);

        return [new Arg($maxAttempts !== null ? clone $maxAttempts : new Int_(3))];
    }

    /**
     * `maxFailures + 1` as a PHPUnit `failureThreshold` expression, or null when it collapses to the
     * default `1` (a literal `maxFailures` of `0`) and should be omitted.
     */
    private function maxFailuresToThreshold(Node\Expr $maxFailures): ?Node\Expr
    {
        if ($maxFailures instanceof Int_) {
            return $maxFailures->value > 0 ? new Int_($maxFailures->value + 1) : null;
        }

        return new Plus($maxFailures, new Int_(1));
    }

    /**
     * The value of the named argument `$name`, or the positional argument at `$position` when it
     * carries no name; null when neither is present.
     *
     * @param array<int, Arg|Node\VariadicPlaceholder> $args
     */
    private function argValue(array $args, string $name, int $position): ?Node\Expr
    {
        foreach ($args as $arg) {
            if ($arg instanceof Arg && $arg->name instanceof Identifier && $arg->name->toString() === $name) {
                return $arg->value;
            }
        }

        $arg = $args[$position] ?? null;
        return $arg instanceof Arg && $arg->name === null ? $arg->value : null;
    }
}
