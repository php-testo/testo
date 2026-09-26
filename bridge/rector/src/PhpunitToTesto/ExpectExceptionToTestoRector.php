<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use Rector\Contract\PhpParser\Node\StmtsAwareInterface;
use Rector\NodeTypeResolver\Node\AttributeKey;
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
 * `\Testo\Expect::exception($c)`, and each immediately-following sibling statement is absorbed as a
 * chained modifier and removed:
 *   - `expectExceptionMessage($m)` => `->withMessageContaining($m)` — PHPUnit matches a substring,
 *     so the exact `->withMessage()` would fail tests that passed before;
 *   - `expectExceptionMessageMatches($re)` => `->withMessageMatchingRegex($re)`;
 *   - `expectExceptionCode($n)` => `->withCode($n)`.
 *
 *     $this->expectException(\RuntimeException::class);
 *     $this->expectExceptionMessage('boom');
 *     $this->expectExceptionCode(7);
 *     // becomes
 *     \Testo\Expect::exception(\RuntimeException::class)->withMessageContaining('boom')->withCode(7);
 *
 * Because this needs cross-statement reasoning (which calls belong together, ordering, intervening
 * statements), it operates at the statements level: it matches the enclosing
 * {@see StmtsAwareInterface} node and rewrites its `->stmts`.
 *
 * Conservative by design: only folding calls that are siblings after the `expectException` statement
 * are absorbed, and the only statements allowed between them are assignments that cannot throw
 * (`$message = 'Type "float" is not supported.';`). The chain then takes the place of the last
 * absorbed call, after those assignments. Any other statement ends the run: code that may throw
 * decides which expectations PHPUnit had registered by then. A bare
 * `expectExceptionMessage`/`Code` with no preceding `expectException` is left untouched.
 *
 * Only folds inside a class: the expectations belong to a test method, so a run in a free function
 * or at namespace level is left untouched.
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
                        \Testo\Expect::exception(\RuntimeException::class)->withMessageContaining('boom')->withCode(7);
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

        # Only fold inside a class: exception expectations belong to a test method. A run of
        # `$this->expect*` in a free function or at namespace level is left untouched.
        if (!$this->isInClassScope($node)) {
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

            # Absorb the expectExceptionMessage/Code siblings that follow, across assignments that
            # cannot throw. The chain lands where the last modifier stood, after those assignments,
            # so a modifier argument they define (`$message = '…';`) is set by then.
            $between = [];
            $pending = [];
            for ($j = $i + 1; $j < $count; ++$j) {
                $next = $stmts[$j];
                $modifier = $next instanceof Expression ? $this->matchModifier($next) : null;
                if ($modifier !== null) {
                    $chain = new MethodCall($chain, new Identifier($modifier[0]), $modifier[1]);
                    \array_push($between, ...$pending);
                    $pending = [];
                    $i = $j;
                    continue;
                }

                if (!$this->isSafeAssignment($next)) {
                    break;
                }

                $pending[] = $next;
            }

            \assert($stmt instanceof Expression);
            $stmt->expr = $chain;
            \array_push($result, ...$between);
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
     * Maps a foldable `$this->expectExceptionMessage/MessageMatches/Code(...)` statement to a `[method, args]`
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
            $this->isName($expr->name, 'expectExceptionMessage') => ['withMessageContaining', $expr->args],
            $this->isName($expr->name, 'expectExceptionMessageMatches') => ['withMessageMatchingRegex', $expr->args],
            $this->isName($expr->name, 'expectExceptionCode') => ['withCode', $expr->args],
            default => null,
        };
    }

    /**
     * Whether the statement assigns a value that cannot throw to a plain variable: a scalar, a
     * constant, or a concatenation or array built from them and from variables.
     */
    private function isSafeAssignment(Node $stmt): bool
    {
        return $stmt instanceof Expression
            && $stmt->expr instanceof Assign
            && $stmt->expr->var instanceof Variable
            && $this->isSafeValue($stmt->expr->expr);
    }

    private function isSafeValue(Expr $expr): bool
    {
        return match (true) {
            $expr instanceof Scalar, $expr instanceof ConstFetch, $expr instanceof Variable => true,
            $expr instanceof ClassConstFetch => $expr->class instanceof Name && $expr->name instanceof Identifier,
            $expr instanceof Concat => $this->isSafeValue($expr->left) && $this->isSafeValue($expr->right),
            $expr instanceof Array_ => $this->isSafeArray($expr),
            default => false,
        };
    }

    private function isSafeArray(Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if ($item->unpack
                || ($item->key !== null && !$this->isSafeValue($item->key))
                || !$this->isSafeValue($item->value)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the statements node sits inside a class. Exception expectations are only folded there;
     * a run in a free function or at namespace level is left untouched.
     */
    private function isInClassScope(Node $node): bool
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);

        return $scope instanceof Scope && $scope->isInClass();
    }
}
