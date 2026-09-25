<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoPolish;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Turns a test that only declares an exception and runs one statement into the attribute form:
 *
 *     public function rejectsEmptyName(): never
 *     {
 *         \Testo\Expect::exception(\InvalidArgumentException::class);
 *         new User('');
 *     }
 *     // becomes
 *     #[\Testo\Assert\ExpectException(\InvalidArgumentException::class)]
 *     public function rejectsEmptyName(): never
 *     {
 *         new User('');
 *     }
 *
 * The attribute registers the expectation before the body runs, while the call registers it where
 * it stands. The two only agree when the call opens the body, so the body must be exactly the
 * bare `Expect::exception(X::class)` followed by the one statement under test. A chained modifier
 * (`->withMessage()`, `->withCode()`, …), a specimen object or `same: true` has no attribute
 * counterpart and leaves the test unchanged.
 *
 * Only a test is touched — a `#[\Testo\Test]` method or function, or a public `void`/`never` method of
 * a class carrying `#[\Testo\Test]` — since the attribute does nothing on a helper. The rule walks the
 * class rather than its methods so it sees the method attributes before
 * {@see ClassLevelTestAttributeRector} moves them onto the class.
 */
#[TestRectorFixtures('ExpectExceptionToAttributeRector')]
final class ExpectExceptionToAttributeRector extends AbstractRector
{
    private const ATTRIBUTE = 'Testo\\Assert\\ExpectException';
    private const TEST = 'Testo\\Test';

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Move a leading bare Expect::exception(X::class) into #[ExpectException] when one statement follows',
            [
                new CodeSample(
                    <<<'PHP'
                        public function rejectsEmptyName(): never
                        {
                            \Testo\Expect::exception(\InvalidArgumentException::class);
                            new User('');
                        }
                        PHP,
                    <<<'PHP'
                        #[\Testo\Assert\ExpectException(\InvalidArgumentException::class)]
                        public function rejectsEmptyName(): never
                        {
                            new User('');
                        }
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Class_::class, Function_::class];
    }

    /**
     * @param Class_|Function_ $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Function_) {
            return $this->hasAttribute($node, self::TEST) && $this->moveExpectation($node) ? $node : null;
        }

        $classLevel = $this->hasAttribute($node, self::TEST);
        $changed = false;
        foreach ($node->getMethods() as $method) {
            $isTest = $this->hasAttribute($method, self::TEST)
                || ($classLevel && $method->isPublic() && $this->returnsVoidOrNever($method));
            $isTest && $this->moveExpectation($method) and $changed = true;
        }

        return $changed ? $node : null;
    }

    /**
     * @return bool Whether the expectation was moved into the attribute.
     */
    private function moveExpectation(ClassMethod|Function_ $node): bool
    {
        $stmts = $node->stmts;
        if ($stmts === null || \count($stmts) !== 2 || !$stmts[0] instanceof Expression) {
            return false;
        }

        $class = $this->matchExpectedClass($stmts[0]);
        if ($class === null || $this->hasAttribute($node, self::ATTRIBUTE)) {
            return false;
        }

        $node->stmts = [$stmts[1]];

        $group = new AttributeGroup([
            new Attribute(new FullyQualified(self::ATTRIBUTE), [new Arg($class)]),
        ]);
        # A position-less group would pull the method's start line to 0 once it leads the list.
        $group->setAttribute('startLine', $node->getStartLine());
        $node->attrGroups[] = $group;

        return true;
    }

    /**
     * The class argument of a bare `\Testo\Expect::exception(X::class)` statement, or null.
     */
    private function matchExpectedClass(Expression $stmt): ClassConstFetch|String_|null
    {
        $call = $stmt->expr;
        if (!$call instanceof StaticCall
            || !$this->isName($call->class, 'Testo\\Expect')
            || !$this->isName($call->name, 'exception')
            || \count($call->args) !== 1
        ) {
            return null;
        }

        $arg = $call->args[0];
        if (!$arg instanceof Arg || ($arg->name !== null && !$this->isName($arg->name, 'classOrObject'))) {
            return null;
        }

        $value = $arg->value;

        return match (true) {
            $value instanceof ClassConstFetch
                && $value->class instanceof Name
                && $this->isName($value->name, 'class') => $value,
            $value instanceof String_ => $value,
            default => null,
        };
    }

    private function returnsVoidOrNever(ClassMethod $method): bool
    {
        return $method->returnType instanceof Identifier
            && \in_array($method->returnType->toLowerString(), ['void', 'never'], true);
    }

    /**
     * @param non-empty-string $name
     */
    private function hasAttribute(Class_|ClassMethod|Function_ $node, string $name): bool
    {
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($this->isName($attr->name, $name)) {
                    return true;
                }
            }
        }

        return false;
    }
}
