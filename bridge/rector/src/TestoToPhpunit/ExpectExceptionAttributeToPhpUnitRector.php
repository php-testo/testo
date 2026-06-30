<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites the `#[\Testo\Assert\ExpectException($class)]` attribute into PHPUnit's statement form
 * `$this->expectException($class);` prepended to the method body.
 *
 * Testo can declare the expectation as an attribute; PHPUnit removed its `ExpectException`
 * attribute long ago and offers only the imperative `$this->expectException(...)` call, which must
 * run before the throwing code. So the attribute is dropped and the call is inserted as the first
 * statement:
 *
 *     #[\Testo\Assert\ExpectException(\InvalidArgumentException::class)]
 *     public function rejectsBadInput(): void
 *     {
 *         new Thing('bad');
 *     }
 *     // becomes
 *     public function rejectsBadInput(): void
 *     {
 *         $this->expectException(\InvalidArgumentException::class);
 *         new Thing('bad');
 *     }
 *
 * The complementary fluent form `\Testo\Expect::exception(...)` (with message/code modifiers) is
 * handled by {@see ExpectExceptionToPhpUnitRector}. An attribute on an abstract/bodyless method is
 * simply dropped — there is nothing to guard.
 */
#[TestRectorFixtures('ExpectExceptionAttributeToPhpUnitRector')]
final class ExpectExceptionAttributeToPhpUnitRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert the #[\Testo\Assert\ExpectException($class)] attribute into a prepended $this->expectException($class) statement',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\Testo\Assert\ExpectException(\InvalidArgumentException::class)]
                        public function rejectsBadInput(): void
                        {
                            new Thing('bad');
                        }
                        PHP,
                    <<<'PHP'
                        public function rejectsBadInput(): void
                        {
                            $this->expectException(\InvalidArgumentException::class);
                            new Thing('bad');
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
        $expected = null;
        $remainingGroups = [];

        foreach ($node->attrGroups as $group) {
            $kept = [];
            foreach ($group->attrs as $attr) {
                $arg = $attr->args[0] ?? null;
                if ($expected === null
                    && $this->isName($attr->name, 'Testo\\Assert\\ExpectException')
                    && $arg instanceof Arg
                ) {
                    $expected = $arg->value;
                    continue; // drop this attribute
                }

                $kept[] = $attr;
            }

            if ($kept !== []) {
                $group->attrs = $kept;
                $remainingGroups[] = $group;
            }
        }

        if (!$expected instanceof Expr) {
            return null;
        }

        $node->attrGroups = $remainingGroups;

        // Bodyless (abstract/interface) method: nothing to guard, just drop the attribute.
        if ($node->stmts !== null) {
            $call = new MethodCall(new Variable('this'), new Identifier('expectException'), [new Arg(clone $expected)]);
            $node->stmts = [new Expression($call), ...$node->stmts];
        }

        return $node;
    }
}
