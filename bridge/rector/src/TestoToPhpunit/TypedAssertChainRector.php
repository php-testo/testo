<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\VariadicPlaceholder;
use PHPStan\Analyser\Scope;
use Rector\NodeTypeResolver\Node\AttributeKey;
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
 * When the subject is not a plain variable (e.g. `Assert::array($log->all())->isList()`), it is
 * hoisted into a local first so it is evaluated once instead of re-run per assertion:
 *
 *     $value = $log->all();
 *     $this->assertIsArray($value);
 *     $this->assertIsList($value);
 *
 * The local name (`value`, `value2`, ...) is chosen to not shadow a variable already in scope.
 *
 * Conservative by design: if the head type or ANY matcher in the chain has no faithful PHPUnit
 * counterpart (JSON path/structure assertions, `every()`, `sameSizeAs()`, custom matchers), the
 * whole chain is left untouched rather than half-converted. Those remain a TODO.
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

        # Only decompose inside a class: the emitted `$this->assert*` statements need a method scope.
        # A chain in a free function or at namespace level is left untouched.
        if (!$this->isInClassScope($node)) {
            return null;
        }

        # Emit `$this->assert*` where `$this` is bound, but `self::assert*` where it is not — a static
        # method, a static closure or a data provider — since PHPUnit's assertions are static and
        # `$this` there is a fatal "using $this when not in object context".
        $useThis = $this->isThisAvailable($node);

        $headArg = $cursor->args[0] ?? null;
        if (!$headArg instanceof Arg) {
            return null;
        }

        # The subject is asserted in the head AND in every matcher. If it is anything other than a
        # plain variable (a method call, property fetch, ...) hoisting it into a local avoids
        # re-evaluating it — and re-running its side effects — once per emitted assertion.
        $prefix = [];
        $subject = $headArg->value;
        if (!$subject instanceof Variable) {
            $variable = new Variable($this->freeVariableName($node));
            $prefix[] = new Expression(new Assign($variable, $subject));
            $subject = $variable;
        }

        $stmts = [...$prefix, $this->assertStmt($assertIs, [$this->arg($subject)], $useThis)];

        foreach (\array_reverse($links) as $link) {
            $matcher = $this->getName($link->name);
            $mapped = $matcher === null ? null : $this->mapMatcher($type, $matcher, $link->args, $subject, $useThis);
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
     * A `value`-based local name not already defined in the statement's scope (`value`, `value2`,
     * `value3`, ...), so hoisting the subject never shadows an existing variable.
     */
    private function freeVariableName(Expression $node): string
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);

        $name = 'value';
        $suffix = 1;
        while ($scope instanceof Scope && $scope->hasVariableType($name)->yes()) {
            $name = 'value' . (++$suffix);
        }

        return $name;
    }

    /**
     * Whether $node sits inside a class. Outside one — a free function or namespace-level code — the
     * emitted `$this->assert*` calls would have no valid target, so the chain is left unchanged.
     */
    private function isInClassScope(Expression $node): bool
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);

        return $scope instanceof Scope && $scope->isInClass();
    }

    /**
     * Whether `$this` is bound in the scope of $node — false inside a static method, a static closure
     * or a data provider, where the assertions must be emitted as `self::assert*` instead.
     */
    private function isThisAvailable(Expression $node): bool
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);

        return $scope instanceof Scope && $scope->hasVariableType('this')->yes();
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
    private function mapMatcher(string $type, string $matcher, array $args, Expr $value, bool $useThis): ?array
    {
        $first = ($args[0] ?? null) instanceof Arg ? $args[0]->value : null;

        # `assertX($needle, $value)` — the common "subject is the last argument" shape.
        $needleFirst = fn(string $assert): ?array => $first === null
            ? null
            : [$this->assertStmt($assert, [$this->arg($first), $this->arg($value)], $useThis)];

        return match (true) {
            ($type === 'int' || $type === 'float') && $matcher === 'greaterThan' => $needleFirst('assertGreaterThan'),
            ($type === 'int' || $type === 'float') && $matcher === 'greaterThanOrEqual' => $needleFirst('assertGreaterThanOrEqual'),
            ($type === 'int' || $type === 'float') && $matcher === 'lessThan' => $needleFirst('assertLessThan'),
            ($type === 'int' || $type === 'float') && $matcher === 'lessThanOrEqual' => $needleFirst('assertLessThanOrEqual'),
            ($type === 'int' || $type === 'float') && $matcher === 'between' => $this->between($args, $value, $useThis),

            $type === 'string' && $matcher === 'contains' => $needleFirst('assertStringContainsString'),
            $type === 'string' && $matcher === 'notContains' => $needleFirst('assertStringNotContainsString'),

            $type === 'array' && $matcher === 'contains' => $needleFirst('assertContains'),
            $type === 'array' && $matcher === 'notContains' => $needleFirst('assertNotContains'),
            $type === 'array' && $matcher === 'hasCount' => $needleFirst('assertCount'),
            $type === 'array' && $matcher === 'notEmpty' => [$this->assertStmt('assertNotEmpty', [$this->arg($value)], $useThis)],
            $type === 'array' && $matcher === 'isList' => [$this->assertStmt('assertIsList', [$this->arg($value)], $useThis)],
            $type === 'array' && $matcher === 'hasKeys' => $this->keys('assertArrayHasKey', $args, $value, $useThis),
            $type === 'array' && $matcher === 'doesNotHaveKeys' => $this->keys('assertArrayNotHasKey', $args, $value, $useThis),
            $type === 'array' && $matcher === 'sameElementsAs' => $needleFirst('assertEqualsCanonicalizing'),

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
    private function between(array $args, Expr $value, bool $useThis): ?array
    {
        $lo = ($args[0] ?? null) instanceof Arg ? $args[0]->value : null;
        $hi = ($args[1] ?? null) instanceof Arg ? $args[1]->value : null;
        if ($lo === null || $hi === null) {
            return null;
        }

        return [
            $this->assertStmt('assertGreaterThanOrEqual', [$this->arg($lo), $this->arg($value)], $useThis),
            $this->assertStmt('assertLessThanOrEqual', [$this->arg($hi), $this->arg($value)], $useThis),
        ];
    }

    /**
     * Variadic key matcher → one `assertArrayHasKey`/`assertArrayNotHasKey` per key.
     *
     * @param non-empty-string $assert
     * @param array<int, Arg|VariadicPlaceholder> $args
     * @return list<Expression>|null
     */
    private function keys(string $assert, array $args, Expr $value, bool $useThis): ?array
    {
        $stmts = [];
        foreach ($args as $arg) {
            if (!$arg instanceof Arg) {
                return null;
            }
            $stmts[] = $this->assertStmt($assert, [$this->arg($arg->value), $this->arg($value)], $useThis);
        }

        return $stmts === [] ? null : $stmts;
    }

    /**
     * One `$this->assert*()` (or `self::assert*()` where `$this` is unavailable) statement.
     *
     * @param non-empty-string $method
     * @param list<Arg> $args
     */
    private function assertStmt(string $method, array $args, bool $useThis): Expression
    {
        $call = $useThis
            ? new MethodCall(new Variable('this'), new Identifier($method), $args)
            : new StaticCall(new Name('self'), new Identifier($method), $args);

        return new Expression($call);
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
