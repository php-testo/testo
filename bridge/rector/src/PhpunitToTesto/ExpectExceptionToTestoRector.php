<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites the bare PHPUnit `$this->expectException($class)` (also `self::`/`static::`)
 * call into a `\Testo\Expect::exception($class)` statement.
 *
 * Scope is intentionally limited to the bare `expectException` call. The fluent
 * companions `expectExceptionMessage()` / `expectExceptionCode()` are NOT folded
 * into a `\Testo\Expect::exception(...)->withMessage(...)->withCode(...)` chain:
 * doing so reliably requires reasoning across sibling statements (which calls
 * belong together, ordering, intervening statements), which is fragile in a
 * node-local Rector rule. Those calls are left in place and flagged.
 *
 * @todo Combine consecutive expectExceptionMessage()/expectExceptionCode() calls
 *       into a fluent ->withMessage()/->withCode() chain on the produced
 *       \Testo\Expect::exception(...) expression. See TODO.md.
 */
#[TestRectorFixtures('ExpectExceptionToTestoRector')]
final class ExpectExceptionToTestoRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert PHPUnit $this->expectException($class) into \Testo\Expect::exception($class)',
            [
                new CodeSample(
                    <<<'PHP'
                        $this->expectException(\RuntimeException::class);
                        PHP,
                    <<<'PHP'
                        \Testo\Expect::exception(\RuntimeException::class);
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    /**
     * @param Expression $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        $expr = $node->expr;

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

        if (!$this->isName($expr->name, 'expectException')) {
            return null;
        }

        $node->expr = new StaticCall(
            new FullyQualified('Testo\\Expect'),
            new Identifier('exception'),
            $expr->args,
        );

        return $node;
    }
}
