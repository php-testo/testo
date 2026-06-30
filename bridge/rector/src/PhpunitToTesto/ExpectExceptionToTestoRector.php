<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Expression;
use Rector\Contract\PhpParser\Node\StmtsAwareInterface;
use Rector\PhpParser\Enum\NodeGroup;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Folds a run of PHPUnit `$this->expect*` exception statements into a single fluent
 * `\Testo\Expect::exception(...)` chain.
 *
 * The head `$this->expectException($c)` (also `self::`/`static::`) becomes
 * `\Testo\Expect::exception($c)`, and each immediately-following sibling
 * `$this->expectExceptionMessage($m)` / `$this->expectExceptionCode($n)` statement is absorbed as a
 * chained `->withMessage($m)` / `->withCode($n)` and removed:
 *
 *     $this->expectException(\RuntimeException::class);
 *     $this->expectExceptionMessage('boom');
 *     $this->expectExceptionCode(7);
 *     // becomes
 *     \Testo\Expect::exception(\RuntimeException::class)->withMessage('boom')->withCode(7);
 *
 * Because this needs cross-statement reasoning (which calls belong together, ordering, intervening
 * statements), it operates at the statements level: it matches the enclosing
 * {@see StmtsAwareInterface} node and rewrites its `->stmts`.
 *
 * Conservative by design: only an UNINTERRUPTED run of folding calls that are direct siblings right
 * after the `expectException` statement is absorbed. The first non-folding statement (including
 * `expectExceptionMessageMatches`, whose regex argument has no faithful `withMessage*` counterpart)
 * ends the run; statements are never reordered or pulled across other code. A bare
 * `expectExceptionMessage`/`Code` with no preceding `expectException` is left untouched.
 */
#[TestRectorFixtures('ExpectExceptionToTestoRector')]
final class ExpectExceptionToTestoRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert PHPUnit `$this->expectException()` (with consecutive expectExceptionMessage/Code) into a fluent `\Testo\Expect::exception()` chain',
            [
                new CodeSample(
                    <<<'PHP'
                        $this->expectException(\RuntimeException::class);
                        $this->expectExceptionMessage('boom');
                        $this->expectExceptionCode(7);
                        PHP,
                    <<<'PHP'
                        \Testo\Expect::exception(\RuntimeException::class)->withMessage('boom')->withCode(7);
                        PHP,
                ),
            ],
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    #[\Override]
    public function getNodeTypes(): array
    {
        return NodeGroup::STMTS_AWARE;
    }

    /**
     * @param StmtsAwareInterface $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        $stmts = $node->stmts;
        if ($stmts === null) {
            return null;
        }

        $changed = false;
        $result = [];
        $count = \count($stmts);

        for ($i = 0; $i < $count; ++$i) {
            $stmt = $stmts[$i];

            $head = $stmt instanceof Expression ? $this->matchExpectException($stmt) : null;
            if ($head === null) {
                $result[] = $stmt;
                continue;
            }

            $chain = new StaticCall(
                new FullyQualified('Testo\\Expect'),
                new Identifier('exception'),
                $head->args,
            );

            # Absorb the uninterrupted run of expectExceptionMessage/Code siblings.
            while ($i + 1 < $count) {
                $next = $stmts[$i + 1];
                $modifier = $next instanceof Expression ? $this->matchModifier($next) : null;
                if ($modifier === null) {
                    break;
                }

                $chain = new MethodCall($chain, new Identifier($modifier[0]), $modifier[1]);
                ++$i;
            }

            \assert($stmt instanceof Expression);
            $stmt->expr = $chain;
            $result[] = $stmt;
            $changed = true;
        }

        if (!$changed) {
            return null;
        }

        $node->stmts = $result;

        return $node;
    }

    /**
     * Returns the `expectException` call expression if the statement is a bare
     * `$this->expectException(...)` (or `self::`/`static::`), otherwise null.
     */
    private function matchExpectException(Expression $stmt): MethodCall|StaticCall|null
    {
        $expr = $stmt->expr;

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

        return $this->isName($expr->name, 'expectException') ? $expr : null;
    }

    /**
     * Maps a foldable `$this->expectExceptionMessage/Code(...)` statement to a `[method, args]`
     * pair for the fluent chain, or null when the statement is not a foldable modifier.
     *
     * @return array{0: non-empty-string, 1: array<int, Arg|\PhpParser\Node\VariadicPlaceholder>}|null
     */
    private function matchModifier(Expression $stmt): ?array
    {
        $expr = $stmt->expr;

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

        return match (true) {
            $this->isName($expr->name, 'expectExceptionMessage') => ['withMessage', $expr->args],
            $this->isName($expr->name, 'expectExceptionCode') => ['withCode', $expr->args],
            default => null,
        };
    }
}
