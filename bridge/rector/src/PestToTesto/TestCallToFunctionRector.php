<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PestToTesto;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use Rector\Contract\PhpParser\Node\StmtsAwareInterface;
use Rector\PhpParser\Enum\NodeGroup;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Restructures Pest's file-level functional DSL into Testo's function-based tests.
 *
 * Pest declares tests as top-level `test('desc', fn)` / `it('desc', fn)` calls with an optional
 * fluent modifier chain, and lifecycle as `beforeEach(fn)` / `afterEach(fn)` / `beforeAll(fn)` /
 * `afterAll(fn)`. Testo discovers plain file-level functions as tests when they carry
 * `#[\Testo\Test]` (lifecycle hooks carry the matching `Testo\Lifecycle\*` attribute), so every Pest
 * call in this family becomes a free function:
 *
 *     test('adds numbers', function () {
 *         expect(1 + 1)->toBe(2);
 *     });
 *     // becomes
 *     /** adds numbers *\/
 *     #[\Testo\Test]
 *     function test_adds_numbers(): void
 *     {
 *         expect(1 + 1)->toBe(2); // ExpectToAssertRector then rewrites the assertion
 *     }
 *
 * The description string is preserved verbatim as the function's docblock (Testo reads it as the
 * test description) and is the source of a deterministic name: the declarator prefix (`test_` /
 * `it_`) joined with the snake_cased description (`test('adds numbers')` => `test_adds_numbers`,
 * `it('adds numbers')` => `it_adds_numbers`). Collisions within the file get a numeric suffix.
 *
 * The whole fluent chain is consumed in the same pass (it only exists in the unconverted functional
 * form, so it cannot be left for a later rule):
 *   - `->group('a','b')`  => `#[\Testo\Filter\Group('a','b')]`
 *   - `->covers(X::class)` => `#[\Testo\Codecov\Covers(X::class)]` (repeatable)
 *   - `->throws(X::class[, 'msg'])` => prepended `\Testo\Expect::exception(X::class)[->withMessage('msg')]`, return type `never`
 *   - `->skip(['reason'])` => prepended `throw new \Testo\Core\Exception\SkipTest('reason')`
 *   - `->with([ <rows> ])` => one `#[\Testo\Data\DataSet([...])]` per row (array literal only)
 *
 * Operates at the statements level ({@see StmtsAwareInterface}) so the file/namespace body is
 * rewritten as a whole — required for cross-statement collision-free naming.
 *
 * Conservative by design — the entire statement is LEFT UNTOUCHED (so it stays visibly unconverted
 * rather than silently mistranslated) when it cannot be faithfully restructured:
 *   - a non-string-literal description (no deterministic name can be derived);
 *   - a closure that captures outer variables via `use (...)` (they have no home on a function);
 *   - an unknown / unsupported modifier in the chain (`->only`, `->todo`, `->repeat`, `->depends`, a
 *     conditional `->skip(fn () => …)`, `->with('namedDataset')`, …);
 *   - `describe()` blocks, `uses()`, `dataset()` and `arch()` — see PestToTesto/TODO.md.
 *
 * NB: bodies are moved verbatim, including any `$this` usage. Pest binds `$this` to a per-file
 * TestCase proxy; a converted function has no such `$this`. Files relying on `$this`-shared state
 * across `beforeEach`/tests need that state re-expressed by hand (or via Testo's function lifecycle).
 */
#[TestRectorFixtures('TestCallToFunctionRector')]
final class TestCallToFunctionRector extends AbstractRector
{
    /**
     * Pest test-declaring functions => the Testo attribute their generated function carries, and
     * whether the call's first argument is a description string (tests) or the closure (lifecycle).
     *
     * `baseName` is the prefix for description-bearing calls (`test('adds')` => `test_adds`,
     * `it('adds')` => `it_adds`) and the whole name for lifecycle hooks (`beforeEach` => `before_each`).
     *
     * @var array<non-empty-string, array{attribute: non-empty-string, hasDescription: bool, baseName: non-empty-string}>
     */
    private const DECLARATORS = [
        'test' => ['attribute' => 'Testo\\Test', 'hasDescription' => true, 'baseName' => 'test'],
        'it' => ['attribute' => 'Testo\\Test', 'hasDescription' => true, 'baseName' => 'it'],
        'beforeEach' => ['attribute' => 'Testo\\Lifecycle\\BeforeTest', 'hasDescription' => false, 'baseName' => 'before_each'],
        'afterEach' => ['attribute' => 'Testo\\Lifecycle\\AfterTest', 'hasDescription' => false, 'baseName' => 'after_each'],
        'beforeAll' => ['attribute' => 'Testo\\Lifecycle\\BeforeClass', 'hasDescription' => false, 'baseName' => 'before_all'],
        'afterAll' => ['attribute' => 'Testo\\Lifecycle\\AfterClass', 'hasDescription' => false, 'baseName' => 'after_all'],
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert Pest file-level `test()`/`it()`/`beforeEach()`/… calls (and their fluent modifier chain) into Testo `#[\Testo\Test]` / lifecycle free functions',
            [
                new CodeSample(
                    <<<'PHP'
                        it('adds numbers', function () {
                            expect(1 + 1)->toBe(2);
                        })->group('math');
                        PHP,
                    <<<'PHP'
                        /** adds numbers */
                        #[\Testo\Test]
                        #[\Testo\Filter\Group('math')]
                        function it_adds_numbers(): void
                        {
                            expect(1 + 1)->toBe(2);
                        }
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

        # Pre-seed the used-name set with every function already declared in this body, so generated
        # names never clash with hand-written helpers living alongside the tests.
        $used = [];
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Function_) {
                $used[\strtolower($stmt->name->toString())] = true;
            }
        }

        $changed = false;
        $result = [];
        foreach ($stmts as $stmt) {
            $function = $stmt instanceof Expression ? $this->convert($stmt, $used) : null;
            if ($function === null) {
                $result[] = $stmt;
                continue;
            }

            $result[] = $function;
            $changed = true;
        }

        if (!$changed) {
            return null;
        }

        $node->stmts = $result;

        return $node;
    }

    /**
     * Converts one `Expression` statement into a function declaration, or returns null to leave it
     * untouched.
     *
     * @param array<non-empty-string, true> $used
     */
    private function convert(Expression $stmt, array &$used): ?Function_
    {
        # Unwind the fluent chain from the outermost MethodCall down to the root FuncCall.
        $expr = $stmt->expr;
        $chain = [];
        while ($expr instanceof MethodCall) {
            $method = $this->getName($expr->name);
            if ($method === null) {
                return null;
            }

            $chain[] = [$method, $expr->args];
            $expr = $expr->var;
        }

        if (!$expr instanceof FuncCall) {
            return null;
        }

        $callee = $this->getName($expr->name);
        if ($callee === null || !isset(self::DECLARATORS[$callee])) {
            return null;
        }

        $spec = self::DECLARATORS[$callee];
        $args = $expr->args;

        # Resolve the description (tests) and locate the closure argument.
        $description = null;
        $closureArg = null;
        if ($spec['hasDescription']) {
            if (\count($args) !== 2 || !$args[0] instanceof Arg || !$args[1] instanceof Arg) {
                return null;
            }

            if (!$args[0]->value instanceof String_) {
                return null; // non-literal description => no deterministic name
            }

            $description = $args[0]->value->value;
            $closureArg = $args[1]->value;
        } else {
            if (\count($args) !== 1 || !$args[0] instanceof Arg) {
                return null;
            }

            $closureArg = $args[0]->value;
        }

        # Extract body statements and parameters from the closure / arrow function.
        $body = $this->bodyOf($closureArg);
        if ($body === null) {
            return null;
        }

        [$params, $stmts] = $body;

        # Process the modifier chain (in source order: it was collected outer-first, so reverse).
        $attrGroups = [];
        $prepend = [];
        $returnType = 'void';
        foreach (\array_reverse($chain) as [$method, $modifierArgs]) {
            $outcome = $this->applyModifier($method, $modifierArgs);
            if ($outcome === null) {
                return null; // unknown / unsupported modifier => leave the whole statement
            }

            foreach ($outcome['attributes'] as $attr) {
                $attrGroups[] = $attr;
            }
            foreach ($outcome['prepend'] as $pre) {
                $prepend[] = $pre;
            }
            if ($outcome['returnType'] !== null) {
                $returnType = $outcome['returnType'];
            }
        }

        $name = $this->functionName($spec['baseName'], $description, $used);

        $function = new Function_($name);
        # Rector's scope refresher reads Function_::$namespacedName (normally set by PhpParser's
        # NameResolver on parse); a synthesized node never went through it, so set it explicitly to
        # avoid an "accessed before initialization" system error that would roll back the change.
        $function->namespacedName = new Name($name);
        $function->params = $params;
        $function->returnType = new Identifier($returnType);
        $function->stmts = [...$prepend, ...$stmts];
        $function->attrGroups = [
            new AttributeGroup([new Attribute(new FullyQualified($spec['attribute']))]),
            ...$attrGroups,
        ];

        if ($description !== null) {
            $function->setDocComment(new Doc('/** ' . \str_replace('*/', '* /', $description) . ' */'));
        }

        return $function;
    }

    /**
     * Returns `[params, stmts]` for a closure / arrow function, or null when the value is neither
     * or captures outer variables (which have no home on a free function).
     *
     * @return array{0: list<Node\Param>, 1: list<Node\Stmt>}|null
     */
    private function bodyOf(Node $value): ?array
    {
        if ($value instanceof Closure) {
            if ($value->uses !== []) {
                return null;
            }

            return [\array_values($value->params), \array_values($value->stmts)];
        }

        if ($value instanceof ArrowFunction) {
            # Arrow functions implicitly capture by value; only convert the parameter-only form.
            return [\array_values($value->params), [new Expression($value->expr)]];
        }

        return null;
    }

    /**
     * Maps one fluent modifier to attributes / prepended statements / a return-type override, or
     * null when the modifier is unsupported (which aborts the whole conversion).
     *
     * @param array<int, Arg|Node\VariadicPlaceholder> $args
     * @return array{attributes: list<AttributeGroup>, prepend: list<Node\Stmt>, returnType: ?non-empty-string}|null
     */
    private function applyModifier(string $method, array $args): ?array
    {
        $none = ['attributes' => [], 'prepend' => [], 'returnType' => null];

        return match ($method) {
            'group' => $args === [] ? null : [
                ...$none,
                'attributes' => [$this->attribute('Testo\\Filter\\Group', $args)],
            ],
            'covers' => $args === [] ? null : [
                ...$none,
                'attributes' => [$this->attribute('Testo\\Codecov\\Covers', $args)],
            ],
            'throws' => $this->throwsModifier($args),
            'skip' => $this->skipModifier($args),
            'with' => $this->withModifier($args),
            default => null,
        };
    }

    /**
     * `->throws(X::class[, 'message'])` => prepended `\Testo\Expect::exception(X)[->withMessage('message')]`,
     * forcing the `never` return type. More than two arguments is unsupported.
     *
     * @param array<int, Arg|Node\VariadicPlaceholder> $args
     * @return array{attributes: list<AttributeGroup>, prepend: list<Node\Stmt>, returnType: ?non-empty-string}|null
     */
    private function throwsModifier(array $args): ?array
    {
        if (!isset($args[0]) || !$args[0] instanceof Arg || \count($args) > 2) {
            return null;
        }

        $call = new StaticCall(new FullyQualified('Testo\\Expect'), new Identifier('exception'), [$args[0]]);

        if (isset($args[1])) {
            if (!$args[1] instanceof Arg) {
                return null;
            }

            $call = new MethodCall($call, new Identifier('withMessage'), [$args[1]]);
        }

        return ['attributes' => [], 'prepend' => [new Expression($call)], 'returnType' => 'never'];
    }

    /**
     * `->skip()` / `->skip('reason')` => prepended `throw new \Testo\Core\Exception\SkipTest(['reason'])`.
     * A conditional skip (`->skip(fn () => …)` / `->skip($bool, 'reason')`) is unsupported.
     *
     * @param array<int, Arg|Node\VariadicPlaceholder> $args
     * @return array{attributes: list<AttributeGroup>, prepend: list<Node\Stmt>, returnType: ?non-empty-string}|null
     */
    private function skipModifier(array $args): ?array
    {
        $skipArgs = [];
        if ($args !== []) {
            if (\count($args) !== 1 || !$args[0] instanceof Arg || !$args[0]->value instanceof String_) {
                return null;
            }

            $skipArgs = [$args[0]];
        }

        $throw = new Throw_(new New_(new FullyQualified('Testo\\Core\\Exception\\SkipTest'), $skipArgs));

        return ['attributes' => [], 'prepend' => [new Expression($throw)], 'returnType' => null];
    }

    /**
     * `->with([ <rows> ])` => one `#[\Testo\Data\DataSet([...])]` per row. Only an inline array
     * literal is supported; a named dataset reference (`->with('emails')`) needs a provider and is
     * unsupported here. Each row that is itself an array literal is spread as the row's arguments; a
     * scalar row becomes a single-argument row.
     *
     * @param array<int, Arg|Node\VariadicPlaceholder> $args
     * @return array{attributes: list<AttributeGroup>, prepend: list<Node\Stmt>, returnType: ?non-empty-string}|null
     */
    private function withModifier(array $args): ?array
    {
        if (\count($args) !== 1 || !$args[0] instanceof Arg) {
            return null;
        }

        $rows = $args[0]->value;
        if (!$rows instanceof Node\Expr\Array_) {
            return null;
        }

        $attributes = [];
        foreach ($rows->items as $item) {
            if (!$item instanceof Node\Expr\ArrayItem || $item->key !== null) {
                return null;
            }

            $rowArray = $item->value instanceof Node\Expr\Array_
                ? $item->value
                : new Node\Expr\Array_([new Node\Expr\ArrayItem($item->value)]);

            $attributes[] = $this->attribute('Testo\\Data\\DataSet', [new Arg($rowArray)]);
        }

        return ['attributes' => $attributes, 'prepend' => [], 'returnType' => null];
    }

    /**
     * @param array<int, Arg|Node\VariadicPlaceholder> $args
     */
    private function attribute(string $fqcn, array $args): AttributeGroup
    {
        return new AttributeGroup([new Attribute(new FullyQualified($fqcn), $args)]);
    }

    /**
     * Derives a deterministic, unique snake_case function name: the declarator prefix
     * (`test`/`it`/`before_each`/…) joined with the snake_cased description. The prefix guarantees
     * the result is a valid identifier (never empty, digit-leading, or a reserved word), so only
     * uniqueness needs guarding — a clash within the file gets a `_2`, `_3`, … suffix.
     *
     * @param non-empty-string $baseName
     * @param array<non-empty-string, true> $used
     * @return non-empty-string
     */
    private function functionName(string $baseName, ?string $description, array &$used): string
    {
        $slug = $description === null ? '' : \trim((string) \preg_replace('/[^a-z0-9]+/', '_', \strtolower($description)), '_');
        $base = $slug === '' ? $baseName : $baseName . '_' . $slug;

        $name = $base;
        $n = 1;
        while (isset($used[$name])) {
            $name = $base . '_' . (++$n);
        }

        $used[$name] = true;

        return $name;
    }
}
