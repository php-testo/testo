<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites a `\Testo\Expect::exception($class)` chain into the equivalent sequence of PHPUnit
 * `$this->expect*` statements.
 *
 * Testo declares exception expectations up front via the fluent `Expect` facade; PHPUnit uses a
 * run of separate `$this->expect*` calls. The bare head `Expect::exception($c)` becomes
 * `$this->expectException($c)`, and each chained modifier becomes its own PHPUnit statement:
 *
 *     \Testo\Expect::exception(\RuntimeException::class)->withMessage('boom')->withCode(7);
 *     // becomes
 *     $this->expectException(\RuntimeException::class);
 *     $this->expectExceptionMessage('boom');
 *     $this->expectExceptionCode(7);
 *
 * Because one statement becomes many, this rule matches the wrapping `Stmt\Expression` and returns
 * a `Node[]` (the same decomposition pattern as {@see TypedAssertChainRector}).
 *
 * Mapped modifiers: `withMessage` → `expectExceptionMessage`, `withCode` → `expectExceptionCode`,
 * `withMessagePattern` → `expectExceptionMessageMatches` (both take a PCRE pattern, so this is
 * exact).
 *
 * Conservative by design: if ANY chained modifier has no faithful PHPUnit counterpart, the whole
 * chain is left untouched rather than half-converted, so chains that cannot be fully translated are
 * flagged for manual review. This deliberately includes `withMessageContaining($substring)`: it is
 * substring matching, whereas PHPUnit's only message-matcher (`expectExceptionMessageMatches`) takes
 * a regex, so forwarding a literal substring as a pattern would silently change meaning (the mirror
 * `ExpectExceptionMessageMatchesRector` is blocked for the same reason). `fromMethod`,
 * `withPrevious`, `withoutPrevious` have no PHPUnit equivalent and likewise abort the conversion.
 */
#[TestRectorFixtures('ExpectExceptionToPhpUnitRector')]
final class ExpectExceptionToPhpUnitRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert `\Testo\Expect::exception($class)` (with fluent message/code modifiers) into the matching `$this->expect*` statements',
            [
                new CodeSample(
                    <<<'PHP'
                        \Testo\Expect::exception(\RuntimeException::class)->withMessage('boom')->withCode(7);
                        PHP,
                    <<<'PHP'
                        $this->expectException(\RuntimeException::class);
                        $this->expectExceptionMessage('boom');
                        $this->expectExceptionCode(7);
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
     * @return Node[]|null
     */
    #[\Override]
    public function refactor(Node $node): ?array
    {
        $expr = $node->expr;

        # Walk the chain outermost-first down to the head `Expect::exception(...)`.
        $links = [];
        $cursor = $expr;
        while ($cursor instanceof MethodCall) {
            $links[] = $cursor;
            $cursor = $cursor->var;
        }

        if (!$cursor instanceof StaticCall || !$this->isName($cursor->class, 'Testo\\Expect')) {
            return null;
        }

        if (!$this->isName($cursor->name, 'exception')) {
            return null;
        }

        $headArg = $cursor->args[0] ?? null;
        if (!$headArg instanceof Arg) {
            return null;
        }

        $stmts = [$this->expectStmt('expectException', [$this->arg($headArg->value)])];

        foreach (\array_reverse($links) as $link) {
            $modifier = $this->getName($link->name);
            $mapped = $modifier === null ? null : $this->mapModifier($modifier, $link->args);
            if ($mapped === null) {
                # Any unmapped modifier aborts the whole conversion — never half-convert.
                return null;
            }

            $stmts[] = $mapped;
        }

        return $stmts;
    }

    /**
     * Maps one Testo fluent modifier (with its arguments) to a single PHPUnit `$this->expect*`
     * statement, or null when the modifier has no faithful PHPUnit equivalent. Only `withMessage`,
     * `withCode` and the regex `withMessagePattern` are translated; everything else (notably the
     * substring `withMessageContaining`) returns null and aborts the chain conversion.
     *
     * @param non-empty-string $modifier
     * @param array<int, Arg|\PhpParser\Node\VariadicPlaceholder> $args
     */
    private function mapModifier(string $modifier, array $args): ?Expression
    {
        $first = ($args[0] ?? null) instanceof Arg ? $args[0]->value : null;
        if ($first === null) {
            return null;
        }

        return match ($modifier) {
            'withMessage' => $this->expectStmt('expectExceptionMessage', [$this->arg($first)]),
            'withCode' => $this->expectStmt('expectExceptionCode', [$this->arg($first)]),
            'withMessagePattern' => $this->expectStmt('expectExceptionMessageMatches', [$this->arg($first)]),
            default => null,
        };
    }

    /**
     * @param non-empty-string $method
     * @param list<Arg> $args
     */
    private function expectStmt(string $method, array $args): Expression
    {
        return new Expression(new MethodCall(new Variable('this'), new Identifier($method), $args));
    }

    /**
     * Clone the expression so the rewritten head argument is a distinct node instance, keeping the
     * printed tree well-formed.
     */
    private function arg(Expr $expr): Arg
    {
        return new Arg(clone $expr);
    }
}
