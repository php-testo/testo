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
use PhpParser\Node\VariadicPlaceholder;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Decomposes a Testo fluent typed-assertion chain into a sequence of plain PHPUnit
 * `$this->assert*` statements.
 *
 * The chain head `Assert::<type>($value)` asserts the type and is emitted as the matching
 * `assertIs<Type>($value)`; each chained matcher becomes its own `assert*` line with `$value`
 * as the subject. One matcher may expand into several lines (e.g. `hasKeys('a', 'b')` →
 * one `assertArrayHasKey` per key; `between($lo, $hi)` → two bounds checks):
 *
 *     Assert::string($s)->contains('foo')->notContains('bar');
 *     // becomes
 *     $this->assertIsString($s);
 *     $this->assertStringContainsString('foo', $s);
 *     $this->assertStringNotContainsString('bar', $s);
 *
 * Because one statement becomes many, this rule matches the wrapping `Stmt\Expression` and
 * returns a `Node[]` (allowed by {@see \Rector\Contract\Rector\RectorInterface::refactor()}).
 *
 * Conservative by design: if the head type or ANY matcher in the chain has no faithful PHPUnit
 * counterpart (JSON path/structure assertions, `isList()`, `every()`, `sameSizeAs()`, custom
 * matchers), the whole chain is left untouched rather than half-converted. Those remain a TODO.
 */
#[TestRectorFixtures('TypedAssertChainRector')]
final class TypedAssertChainRector extends AbstractRector
{
    /**
     * Testo `Assert::<type>()` head => PHPUnit type assertion.
     *
     * @var array<non-empty-string, non-empty-string>
     */
    private const TYPE_TO_ASSERT_IS = [
        'string' => 'assertIsString',
        'int' => 'assertIsInt',
        'float' => 'assertIsFloat',
        'array' => 'assertIsArray',
        'object' => 'assertIsObject',
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Decompose Testo fluent typed-assertion chains into separate PHPUnit assert* statements',
            [
                new CodeSample(
                    <<<'PHP'
                        \Testo\Assert::int($n)->greaterThan(0)->lessThanOrEqual(100);
                        PHP,
                    <<<'PHP'
                        $this->assertIsInt($n);
                        $this->assertGreaterThan(0, $n);
                        $this->assertLessThanOrEqual(100, $n);
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
        if (!$expr instanceof MethodCall) {
            return null;
        }

        # Walk the chain outermost-first down to the head `Assert::<type>(...)`.
        $links = [];
        $cursor = $expr;
        while ($cursor instanceof MethodCall) {
            $links[] = $cursor;
            $cursor = $cursor->var;
        }

        if (!$cursor instanceof StaticCall || !$this->isName($cursor->class, 'Testo\\Assert')) {
            return null;
        }

        $type = $this->getName($cursor->name);
        $assertIs = $type === null ? null : (self::TYPE_TO_ASSERT_IS[$type] ?? null);
        if ($assertIs === null) {
            return null;
        }

        $headArg = $cursor->args[0] ?? null;
        if (!$headArg instanceof Arg) {
            return null;
        }
        $value = $headArg->value;

        $stmts = [$this->assertStmt($assertIs, [$this->arg($value)])];

        foreach (\array_reverse($links) as $link) {
            $matcher = $this->getName($link->name);
            $mapped = $matcher === null ? null : $this->mapMatcher($type, $matcher, $link->args, $value);
            if ($mapped === null) {
                # Any unmapped matcher aborts the whole conversion — never half-convert.
                return null;
            }

            foreach ($mapped as $stmt) {
                $stmts[] = $stmt;
            }
        }

        return $stmts;
    }

    /**
     * Maps one Testo matcher (with its arguments) to one or more PHPUnit assertion statements,
     * or null when the matcher has no faithful PHPUnit equivalent.
     *
     * @param non-empty-string $type
     * @param non-empty-string $matcher
     * @param array<int, Arg|VariadicPlaceholder> $args
     * @return list<Expression>|null
     */
    private function mapMatcher(string $type, string $matcher, array $args, Expr $value): ?array
    {
        $first = ($args[0] ?? null) instanceof Arg ? $args[0]->value : null;

        # `assertX($needle, $value)` — the common "subject is the last argument" shape.
        $needleFirst = fn(string $assert): ?array => $first === null
            ? null
            : [$this->assertStmt($assert, [$this->arg($first), $this->arg($value)])];

        return match (true) {
            ($type === 'int' || $type === 'float') && $matcher === 'greaterThan' => $needleFirst('assertGreaterThan'),
            ($type === 'int' || $type === 'float') && $matcher === 'greaterThanOrEqual' => $needleFirst('assertGreaterThanOrEqual'),
            ($type === 'int' || $type === 'float') && $matcher === 'lessThan' => $needleFirst('assertLessThan'),
            ($type === 'int' || $type === 'float') && $matcher === 'lessThanOrEqual' => $needleFirst('assertLessThanOrEqual'),
            ($type === 'int' || $type === 'float') && $matcher === 'between' => $this->between($args, $value),

            $type === 'string' && $matcher === 'contains' => $needleFirst('assertStringContainsString'),
            $type === 'string' && $matcher === 'notContains' => $needleFirst('assertStringNotContainsString'),

            $type === 'array' && $matcher === 'contains' => $needleFirst('assertContains'),
            $type === 'array' && $matcher === 'notContains' => $needleFirst('assertNotContains'),
            $type === 'array' && $matcher === 'hasCount' => $needleFirst('assertCount'),
            $type === 'array' && $matcher === 'notEmpty' => [$this->assertStmt('assertNotEmpty', [$this->arg($value)])],
            $type === 'array' && $matcher === 'hasKeys' => $this->keys('assertArrayHasKey', $args, $value),
            $type === 'array' && $matcher === 'doesNotHaveKeys' => $this->keys('assertArrayNotHasKey', $args, $value),

            $type === 'object' && $matcher === 'instanceOf' => $needleFirst('assertInstanceOf'),
            $type === 'object' && $matcher === 'hasProperty' => $needleFirst('assertObjectHasProperty'),

            default => null,
        };
    }

    /**
     * `between($lo, $hi)` → two bound checks against `$value`.
     *
     * @param array<int, Arg|VariadicPlaceholder> $args
     * @return list<Expression>|null
     */
    private function between(array $args, Expr $value): ?array
    {
        $lo = ($args[0] ?? null) instanceof Arg ? $args[0]->value : null;
        $hi = ($args[1] ?? null) instanceof Arg ? $args[1]->value : null;
        if ($lo === null || $hi === null) {
            return null;
        }

        return [
            $this->assertStmt('assertGreaterThanOrEqual', [$this->arg($lo), $this->arg($value)]),
            $this->assertStmt('assertLessThanOrEqual', [$this->arg($hi), $this->arg($value)]),
        ];
    }

    /**
     * Variadic key matcher → one `assertArrayHasKey`/`assertArrayNotHasKey` per key.
     *
     * @param non-empty-string $assert
     * @param array<int, Arg|VariadicPlaceholder> $args
     * @return list<Expression>|null
     */
    private function keys(string $assert, array $args, Expr $value): ?array
    {
        $stmts = [];
        foreach ($args as $arg) {
            if (!$arg instanceof Arg) {
                return null;
            }
            $stmts[] = $this->assertStmt($assert, [$this->arg($arg->value), $this->arg($value)]);
        }

        return $stmts === [] ? null : $stmts;
    }

    /**
     * @param non-empty-string $method
     * @param list<Arg> $args
     */
    private function assertStmt(string $method, array $args): Expression
    {
        return new Expression(new MethodCall(new Variable('this'), new Identifier($method), $args));
    }

    /**
     * Clone the expression for each reuse: `$value` appears once in the source chain but many
     * times in the output, so distinct node instances keep the printed tree well-formed.
     */
    private function arg(Expr $expr): Arg
    {
        return new Arg(clone $expr);
    }
}
