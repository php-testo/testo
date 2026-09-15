<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Turns an unconditional `markTestSkipped()` that opens a test method into Testo's declarative
 * `#[\Testo\Skip]` attribute, dropping the call.
 *
 * A skip on the first line is a property of the test, not of a code path through it, and that is
 * what the attribute expresses: Testo keeps such a test out of the per-test pipeline entirely —
 * `#[BeforeTest]`, the data provider and `#[Retry]`/`#[Repeat]` never engage, and the case class is
 * never constructed. A literal message becomes the reason.
 *
 * Everything else stays a throw, converted by {@see MarkTestSkippedToTestoRector}: a call deeper in
 * the body (guarded by an `if`, inside a loop) is a runtime decision, and a non-literal message
 * (`$this->markTestSkipped($reason)`, a concatenation with a variable) cannot live in an attribute
 * argument. `self::`/`static::` calls convert the same way as `$this->`.
 *
 * **Residual:** a `markTestSkipped()` opening `setUp()` skips every test of the class in PHPUnit;
 * that stays a throw rather than becoming a class-level `#[Skip]`, so the hook keeps whatever else
 * it does.
 */
#[TestRectorFixtures('MarkTestSkippedToSkipAttributeRector')]
final class MarkTestSkippedToSkipAttributeRector extends AbstractRector
{
    private const SKIP_TESTO = 'Testo\\Skip';

    /** @var list<string> */
    private const TEST_ATTRIBUTES = ['PHPUnit\\Framework\\Attributes\\Test', 'Testo\\Test'];

    /** @var list<string> */
    private const LIFECYCLE_NAMES = ['setup', 'teardown', 'setupbeforeclass', 'teardownafterclass'];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert a leading unconditional markTestSkipped() into the Testo #[Skip] attribute',
            [
                new CodeSample(
                    <<<'PHP'
                        public function testSomething(): void
                        {
                            $this->markTestSkipped('not ready');
                            $this->doSomething();
                        }
                        PHP,
                    <<<'PHP'
                        #[\Testo\Skip('not ready')]
                        public function testSomething(): void
                        {
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
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if ($node->stmts === null || !$this->isTestMethod($node)) {
            return null;
        }

        $first = $node->stmts[0] ?? null;
        if (!$first instanceof Expression) {
            return null;
        }

        $args = $this->skipCallArgs($first->expr);
        if ($args === null) {
            return null;
        }

        \array_shift($node->stmts);
        $node->attrGroups[] = new AttributeGroup([
            new Attribute(new FullyQualified(self::SKIP_TESTO), $args),
        ]);

        return $node;
    }

    /**
     * The `#[Skip]` arguments for a `markTestSkipped()` call expression: none for a message-less
     * call, the literal message otherwise. Null when the expression is not such a call at all, or
     * carries a message an attribute cannot hold — a non-literal one stays a throw.
     *
     * @return list<Arg>|null
     */
    private function skipCallArgs(Node\Expr $expr): ?array
    {
        if ($expr instanceof MethodCall) {
            if (!$this->isName($expr->var, 'this')) {
                return null;
            }
        } elseif ($expr instanceof StaticCall) {
            if (!$this->isName($expr->class, 'self') && !$this->isName($expr->class, 'static')) {
                return null;
            }
        } else {
            return null;
        }

        if (!$this->isName($expr->name, 'markTestSkipped')) {
            return null;
        }

        $message = $expr->args[0] ?? null;
        if ($message === null) {
            return [];
        }

        return $message instanceof Arg && $message->value instanceof String_
            ? [new Arg(new String_($message->value->value))]
            : null;
    }

    /**
     * Whether the method is a PHPUnit test: public, non-static, not a lifecycle hook, and either
     * `test`-prefixed or carrying a `#[Test]` attribute (PHPUnit's or the converted Testo one).
     */
    private function isTestMethod(ClassMethod $method): bool
    {
        if (!$method->isPublic() || $method->isStatic()) {
            return false;
        }

        $name = \strtolower((string) $this->getName($method));
        if (\in_array($name, self::LIFECYCLE_NAMES, true)) {
            return false;
        }

        return \str_starts_with($name, 'test') || $this->hasTestAttribute($method);
    }

    private function hasTestAttribute(ClassMethod $method): bool
    {
        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                foreach (self::TEST_ATTRIBUTES as $name) {
                    if ($this->isName($attr->name, $name)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
