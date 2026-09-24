<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\MockeryToDouble;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\VariadicPlaceholder;
use PhpParser\NodeFinder;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Internal\PhpunitConstraint;
use Testo\Bridge\Rector\Internal\PredicateVariable;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;
use Testo\Codecov\Covers;

/**
 * Converts Mockery doubles — creation, expectation chains, spy verification and `Mockery::close()` —
 * into the equivalent {@see \JMac\Testing\Double} form (the `testo/bridge-double` package):
 *
 *     $dep = \Mockery::mock(Dependency::class);
 *     $dep->shouldReceive('run')->once()->with('x')->andReturn('y');
 *     // becomes
 *     $dep = \JMac\Testing\Double::for(Dependency::class)->strict();
 *     $dep->expects('run')->with('x')->returns('y');
 *
 * Creation: `Mockery::mock(X)` → `Double::for(X)->strict()` (a plain Mockery mock rejects an
 * unconfigured call, which is Double's Strict mode), `Mockery::spy(X)` and
 * `Mockery::mock(X)->shouldIgnoreMissing()` → `Double::for(X)` (Loose, the Double default),
 * `Mockery::mock(X)->makePartial()` → `Double::for(X)->passthru()`, and with constructor arguments
 * `Mockery::mock(X, [$a])->makePartial()` → `->passthru(new X($a))`. Several targets — `X::class`
 * arguments or an `'A, B'` / `'A|B'` string — become an intersection double. A computed target converts
 * by its type: a class-string like a literal one, an object as the proxied partial
 * `Double::for($object)->passthru()`. Quick definitions `$m = Mockery::mock(X, ['run' => 1])` and the
 * inline `$m = Mockery::mock(X)->shouldReceive(…)->…->getMock()` are split into the creation plus one
 * statement per expectation.
 *
 * Expectations: `shouldReceive('m')` / `allows('m')` → `allows('m')`, and any call count turns it into
 * `expects('m')` — `once()` is the `expects()` default, `twice()`/`times($n)` → `times($n)`, `never()` →
 * `never()`, `atLeast()->once()/twice()/times($n)` → `times(minimum: …)`, `atMost()->…` →
 * `times(maximum: …)`, `between($a, $b)` → `times($a, $b)`, `zeroOrMoreTimes()` → `allows()`;
 * `shouldNotReceive('m')` → `expects('m')->never()`. The magic `allows()->m($x)` / `expects()->m($x)` /
 * `shouldNotReceive()->m($x)` forms fold the call into `with($x)`, and several methods in one
 * `shouldReceive('a', 'b')` or an `allows(['a' => 1])` / `shouldReceive(['a' => 1])` map become one
 * statement per method. Arguments: `with()` keeps its values, `withArgs([...])` (or an array-typed
 * value) unpacks, `withArgs($closure)` → `with(Argument::all(...))`, `withSomeOfArgs(...)` →
 * `with(Argument::all(<each one given, strictly>))`, `withNoArgs()` → `with(Argument::none())`,
 * `withAnyArgs()` drops away. Returns: `andReturn(s)` → `returns`, `andReturnValues(...)` →
 * `returns(...)`, `andReturnNull/True/False()` → `returns(literal)`, `andReturnSelf()` →
 * `returns(<the double>)`, `andReturnArg($n)` → `resolves(fn (...$args) => $args[$n])`,
 * `andReturnUsing($fn)` → `resolves($fn)`, `andThrow(s)($e)` → `throws($e)` (a class-string form builds
 * the exception), `andThrowExceptions(...)` → `throws(...)`; `ordered()` carries over, a trailing
 * `getMock()` on a statement drops, and `byDefault()` on an expectation with no arguments and no count
 * drops too: Double tries the newest expectation first, so a later one overrides it.
 *
 * Verification: `shouldHaveReceived('m')` → `received('m')`, `shouldNotHaveReceived('m')` →
 * `received('m')->never()`, `shouldHaveBeenCalled()` / `shouldNotHaveBeenCalled()` → the same on
 * `__invoke`, the argument-list and magic `shouldHaveReceived()->m($x)` forms fold into `with()`, and the
 * same count modifiers apply. `Mockery::close()` → `Double::verifyAll()`.
 *
 * Argument matchers become `Argument::*`: `any` → `any`, `type` → `type` (the names Double's `type()`
 * does not know become an `is_*()` predicate, as Mockery checks them), `on` → `satisfies`, `capture` →
 * `capture`, `pattern` → `matches`, `anyOf` → `any(…)`, `notAnyOf` → `not()->any(…)`, `not` → `not`,
 * `isSame` → `same`, `hasValue` / a one-value `contains` → `contains`, `andAnyOtherArgs`/`andAnyOthers`
 * → `remaining`; `mustBe($x)` and `isEqual($x)` unwrap to the bare `$x`; `hasKey`, several-value
 * `contains`, `subset` and `ducktype` become the `satisfies()` predicate Mockery's matcher evaluates.
 *
 * All-or-nothing per statement: a chain carrying any link with no faithful Double form — `passthru()`,
 * `andSet()`, `globally()`, a grouped `ordered()`, a demeter `shouldReceive('a->b')`,
 * `andReturnUndefined()`, `byDefault()` with arguments or a count, a nested matcher, a Hamcrest matcher —
 * is left whole, and so is the Mockery factory it roots on. So is a bare `Mockery::mock()`, the
 * `alias:`/`overload:`/`X[m]` targets, a full mock with constructor arguments, and a computed target of
 * unknown type. Those stay for manual migration (see TODO.md).
 *
 * Comparison is the residual by design: Mockery compares plain argument values loosely (`==`), Double
 * strictly (`===` for scalars, `==` for objects), so a test that leaned on coercion — `'1'` against `1` —
 * fails after conversion and needs its expected value fixed.
 */
#[TestRectorFixtures('MockeryToDoubleRector')]
#[Covers(self::class)]
#[Covers(PhpunitConstraint::class)]
#[Covers(PredicateVariable::class)]
final class MockeryToDoubleRector extends AbstractRector
{
    /**
     * Set on a Mockery factory call whose configuration chain could not be converted, so the call-level
     * visit leaves it as Mockery rather than handing a Double to a Mockery-only method.
     */
    private const KEEP = 'testo_mockery_keep';

    /**
     * Set on the calls this rule builds, so a later visit never mistakes a rebuilt `allows()`/`expects()`
     * rooted on the factory for an unconverted Mockery chain.
     */
    private const REBUILT = 'testo_mockery_rebuilt';

    /**
     * Links that only Mockery spells this way: their presence tells a Mockery `allows('m')`/`expects('m')`
     * chain apart from a Double one, which shares those two entry verbs.
     */
    private const MOCKERY_ONLY_LINKS = [
        'once', 'twice', 'atLeast', 'atMost', 'between', 'zeroOrMoreTimes', 'byDefault',
        'withArgs', 'withNoArgs', 'withAnyArgs', 'withSomeOfArgs',
        'andReturn', 'andReturns', 'andReturnValues', 'andReturnNull', 'andReturnTrue', 'andReturnFalse',
        'andReturnSelf', 'andReturnArg', 'andReturnUsing', 'andThrow', 'andThrows', 'andThrowExceptions',
    ];

    /**
     * The type names Double's `Argument::type()` checks natively; any other name it treats as a class.
     */
    private const DOUBLE_TYPES = ['int', 'float', 'string', 'bool', 'array', 'object', 'callable', 'iterable', 'null'];

    /**
     * Mockery's `type()` checks `is_<name>()` when such a function exists. These names reach a PHP type
     * Double knows under another name…
     */
    private const TYPE_ALIASES = ['integer' => 'int', 'long' => 'int', 'double' => 'float', 'real' => 'float'];

    /**
     * …and these reach an `is_*()` check Double has no type name for.
     */
    private const PREDICATE_TYPES = ['numeric', 'scalar', 'resource', 'countable', 'finite', 'infinite', 'nan'];

    /**
     * The variable the predicates of the statement being rebuilt are written over.
     */
    private string $predicateName = 'value';

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert Mockery `mock()`/`spy()` doubles, their `shouldReceive()`/`allows()`/`expects()` chains and `shouldHaveReceived()` verification into `\JMac\Testing\Double` calls',
            [
                new CodeSample(
                    <<<'PHP'
                        $dep = \Mockery::mock(Dependency::class);
                        $dep->shouldReceive('run')->once()->with('x')->andReturn('y');
                        PHP,
                    <<<'PHP'
                        $dep = \JMac\Testing\Double::for(Dependency::class)->strict();
                        $dep->expects('run')->with('x')->returns('y');
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
        return [Expression::class, MethodCall::class, StaticCall::class];
    }

    /**
     * Statement level rebuilds a whole expectation chain (and `Mockery::close()`), and splits the
     * statements that set up several expectations at once; call level rewrites the factories. Rector
     * visits a statement before the calls inside it, so an unconvertible chain can pin its factory before
     * the factory's own visit.
     *
     * @param Expression|MethodCall|StaticCall $node
     * @return Node|list<Expression>|null
     */
    #[\Override]
    public function refactor(Node $node): Node|array|null
    {
        return match (true) {
            $node instanceof Expression => $this->refactorStatement($node),
            $node instanceof MethodCall => $this->refactorFactoryModifier($node),
            default => $this->refactorFactory($node),
        };
    }

    /**
     * @return Expression|list<Expression>|null
     */
    private function refactorStatement(Expression $node): Expression|array|null
    {
        if ($node->expr instanceof StaticCall && $this->isMockeryCall($node->expr, 'close') && $node->expr->args === []) {
            $node->expr = new StaticCall(new FullyQualified('JMac\\Testing\\Double'), new Identifier('verifyAll'));

            return $node;
        }

        $this->predicateName = PredicateVariable::nameFor($node);

        if ($node->expr instanceof Assign) {
            return $this->refactorAssignment($node->expr);
        }

        if (!$node->expr instanceof MethodCall) {
            return null;
        }

        $segments = $this->segments($node->expr);

        # The result of a trailing `getMock()` goes nowhere in a plain statement, so it drops.
        $last = $segments[\count($segments) - 1];
        if (\count($segments) > 1 && $this->callName($last) === 'getMock' && $last->args === []) {
            \array_pop($segments);
        }

        $chains = $this->rebuildChain($segments[0]->var, $segments);
        if ($chains === null) {
            return null;
        }

        if (\count($chains) === 1) {
            $node->expr = $chains[0];

            return $node;
        }

        return \array_map(static fn(MethodCall $chain): Expression => new Expression($chain), $chains);
    }

    /**
     * Splits the two assignment shapes that create a double and configure it in one expression:
     * the quick definitions `$m = Mockery::mock(X, ['run' => 1])`, and the inline chain
     * `$m = Mockery::mock(X)->shouldReceive('run')->andReturn(1)->getMock()`. Either becomes the Double
     * creation followed by one statement per expectation, on the assigned variable.
     *
     * @return list<Expression>|null
     */
    private function refactorAssignment(Assign $assign): ?array
    {
        if ($this->cloneRoot($assign->var) === null) {
            return null;
        }

        $value = $assign->expr;
        if ($value instanceof StaticCall) {
            return $this->splitQuickDefinitions($assign->var, $value);
        }

        if (!$value instanceof MethodCall || $this->callName($value) !== 'getMock' || $value->args !== []) {
            return null;
        }

        $segments = $this->segments($value);
        \array_pop($segments);
        if ($segments === []) {
            return null;
        }

        $factory = $segments[0]->var;
        if (!$factory instanceof StaticCall || $this->factoryKind($factory) === null) {
            return null;
        }

        # A leading `shouldIgnoreMissing()` / `makePartial()` belongs to the creation, not to the chain.
        $double = $this->modifiedDouble($factory, $segments[0]);
        if ($double !== null) {
            \array_shift($segments);
        } else {
            $double = $this->plainDouble($factory);
        }

        if ($double === null || $segments === []) {
            $factory->setAttribute(self::KEEP, true);

            return null;
        }

        $root = $this->cloneRoot($assign->var);
        \assert($root !== null);
        $chains = $this->rebuildChain($root, $segments);
        if ($chains === null) {
            $factory->setAttribute(self::KEEP, true);

            return null;
        }

        return [
            new Expression(new Assign($assign->var, $double)),
            ...\array_map(static fn(MethodCall $chain): Expression => new Expression($chain), $chains),
        ];
    }

    /**
     * `$m = Mockery::mock(X, ['run' => 1])` → `$m = Double::for(X)->strict(); $m->allows('run')->returns(1);`
     *
     * @return list<Expression>|null
     */
    private function splitQuickDefinitions(Node\Expr $var, StaticCall $factory): ?array
    {
        $kind = $this->factoryKind($factory);
        $map = ($factory->args[1] ?? null) instanceof Arg ? $factory->args[1]->value : null;
        if ($kind === null || \count($factory->args) !== 2 || !$map instanceof Array_) {
            return null;
        }

        $entries = $this->mapEntries($map);
        $targets = $this->factoryTargets(new StaticCall($factory->class, $factory->name, [$factory->args[0]]));
        if ($entries === null || $entries === [] || $targets === null) {
            return null;
        }

        $statements = [new Expression(new Assign($var, $this->creation($kind, $targets)))];
        foreach ($entries as [$method, $return]) {
            $root = $this->cloneRoot($var);
            \assert($root !== null);
            $call = $this->rebuilt(new MethodCall($root, new Identifier('allows'), [new Arg($method)]));
            $statements[] = new Expression($this->rebuilt(new MethodCall($call, new Identifier('returns'), [new Arg($return)])));
        }

        return $statements;
    }

    /**
     * `Mockery::mock(X)->shouldIgnoreMissing()` → `Double::for(X)` and `->makePartial()` →
     * `Double::for(X)->passthru()`. Any other Mockery-only call made straight on a factory — the inline
     * `Mockery::mock(X)->shouldReceive(…)->getMock()` shape outside an assignment — pins the factory,
     * since the chain it heads has no Double form.
     */
    private function refactorFactoryModifier(MethodCall $node): ?Node
    {
        $factory = $node->var;
        if (!$factory instanceof StaticCall || $node->getAttribute(self::REBUILT) === true || $this->factoryKind($factory) === null) {
            return null;
        }

        $double = $this->modifiedDouble($factory, $node);
        if ($double !== null) {
            return $double;
        }

        $factory->setAttribute(self::KEEP, true);

        return null;
    }

    /**
     * `Mockery::mock(X)` → `Double::for(X)->strict()`, `Mockery::spy(X)` → `Double::for(X)`.
     */
    private function refactorFactory(StaticCall $node): ?Node
    {
        return $node->getAttribute(self::KEEP) === true ? null : $this->plainDouble($node);
    }

    /**
     * The Double for a factory call on its own, or null when it has none.
     */
    private function plainDouble(StaticCall $factory): ?Node\Expr
    {
        $kind = $this->factoryKind($factory);
        if ($kind === null) {
            return null;
        }

        # `Mockery::mock($object)` is a proxied partial: the real object answers what is not configured.
        $proxied = $kind === 'mock' ? $this->proxiedObject($factory) : null;
        if ($proxied !== null) {
            return new MethodCall($this->doubleFor([new Arg($proxied)]), new Identifier('passthru'));
        }

        $targets = $this->factoryTargets($factory);

        return $targets === null ? null : $this->creation($kind, $targets);
    }

    /**
     * The Double for a factory followed by `shouldIgnoreMissing()` or `makePartial()`, or null for any
     * other call on the factory.
     */
    private function modifiedDouble(StaticCall $factory, MethodCall $modifier): ?Node\Expr
    {
        if ($this->factoryKind($factory) !== 'mock' || $modifier->args !== []) {
            return null;
        }

        $name = $this->callName($modifier);

        if ($name === 'shouldIgnoreMissing') {
            $targets = $this->factoryTargets($factory);

            return $targets === null ? null : $this->doubleFor($targets);
        }

        if ($name !== 'makePartial') {
            return null;
        }

        # `Mockery::mock(X, [$a, $b])->makePartial()` runs the real constructor with those arguments; a
        # passthru double copies the state of a real instance built the same way.
        $constructorArgs = ($factory->args[1] ?? null) instanceof Arg ? $factory->args[1]->value : null;
        if ($constructorArgs !== null) {
            $items = \count($factory->args) === 2 ? $this->listItems($constructorArgs) : null;
            $targets = $this->factoryTargets(new StaticCall($factory->class, $factory->name, [$factory->args[0]]));
            $class = $targets !== null && \count($targets) === 1 ? $this->className($targets[0]->value) : null;
            if ($items === null || $class === null) {
                return null;
            }

            return new MethodCall(
                $this->doubleFor($targets),
                new Identifier('passthru'),
                [new Arg(new New_($class, \array_map(static fn(Node\Expr $item): Arg => new Arg($item), $items)))],
            );
        }

        $targets = $this->factoryTargets($factory);

        return $targets === null ? null : new MethodCall($this->doubleFor($targets), new Identifier('passthru'));
    }

    /**
     * @param 'mock'|'spy' $kind
     * @param list<Arg> $targets
     */
    private function creation(string $kind, array $targets): Node\Expr
    {
        $double = $this->doubleFor($targets);

        return $kind === 'spy' ? $double : new MethodCall($double, new Identifier('strict'));
    }

    /**
     * `'mock'`/`'spy'` for a `Mockery::mock()`/`Mockery::spy()` call, null for anything else.
     *
     * @return 'mock'|'spy'|null
     */
    private function factoryKind(StaticCall $node): ?string
    {
        return match (true) {
            $this->isMockeryCall($node, 'mock') => 'mock',
            $this->isMockeryCall($node, 'spy') => 'spy',
            default => null,
        };
    }

    /**
     * The doubled types of a factory call when each argument names plain target types: an `X::class`
     * fetch, a string literal of one type or of a `'A, B'` / `'A|B'` list, or a computed value PHPStan
     * knows is a string. Null for no arguments (Double needs a type) or anything Mockery would read as
     * configuration — an array, a closure, an object, the `alias:`/`overload:`/`X[m]` target syntax.
     *
     * @return list<Arg>|null
     */
    private function factoryTargets(StaticCall $node): ?array
    {
        if ($node->args === []) {
            return null;
        }

        $targets = [];
        foreach ($node->args as $arg) {
            if (!$arg instanceof Arg || $arg->name !== null || $arg->unpack) {
                return null;
            }

            $value = $arg->value;
            if ($value instanceof ClassConstFetch && $this->isName($value->name, 'class')) {
                $targets[] = new Arg($value);
                continue;
            }

            if ($value instanceof String_) {
                foreach (\preg_split('/\s*[,|]\s*/', \trim($value->value)) ?: [] as $type) {
                    if (\preg_match('/^\\\\?[A-Za-z_][\w\\\\]*$/', $type) !== 1) {
                        return null;
                    }
                    $targets[] = new Arg(new String_($type));
                }
                continue;
            }

            if ($value instanceof Array_ || $value instanceof Closure || $value instanceof ArrowFunction || !$this->getType($value)->isString()->yes()) {
                return null;
            }

            $targets[] = new Arg($value);
        }

        return $targets;
    }

    /**
     * The single object argument of a `Mockery::mock($object)` call, as PHPStan types it.
     */
    private function proxiedObject(StaticCall $factory): ?Node\Expr
    {
        $arg = $factory->args[0] ?? null;
        if (\count($factory->args) !== 1 || !$arg instanceof Arg || $arg->name !== null || $arg->unpack) {
            return null;
        }

        $value = $arg->value;
        if ($value instanceof Closure || $value instanceof ArrowFunction || $value instanceof New_) {
            return null;
        }

        return $this->getType($value)->isObject()->yes() ? $value : null;
    }

    /**
     * The class named by a target argument, for building a real instance of it.
     */
    private function className(Node\Expr $target): ?Name
    {
        return match (true) {
            $target instanceof ClassConstFetch && $target->class instanceof Name => $target->class,
            $target instanceof String_ => new FullyQualified(\ltrim($target->value, '\\')),
            default => null,
        };
    }

    /**
     * The chain's calls, innermost first.
     *
     * @return non-empty-list<MethodCall>
     */
    private function segments(MethodCall $node): array
    {
        $segments = [];
        $cursor = $node;
        while ($cursor instanceof MethodCall) {
            $segments[] = $cursor;
            $cursor = $cursor->var;
        }

        return \array_reverse($segments);
    }

    /**
     * Rebuilds an expectation or verification chain into its Double form — one chain per method it sets
     * up — or returns null when the statement is not a Mockery chain or carries a link with no faithful
     * counterpart. An unconvertible chain rooted on a Mockery factory pins that factory (see
     * {@see self::KEEP}).
     *
     * @param non-empty-list<MethodCall> $segments
     * @return non-empty-list<MethodCall>|null
     */
    private function rebuildChain(Node\Expr $root, array $segments): ?array
    {
        $entry = $this->parseEntry($segments);
        if ($entry === null) {
            return null;
        }

        $chains = $this->rebuildLinks($root, $entry, \array_slice($segments, $entry['consumed']));
        if ($chains === null && $entry['certain']) {
            $this->pinFactory($root);
        }

        return $chains;
    }

    /**
     * Reads the chain's opening call: the verb, the methods it targets (each with the return value an
     * array form assigns it) and any arguments the entry itself carries (the magic `allows()->m($x)`
     * form, the `shouldHaveReceived('m', [$x])` list). `certain` says whether the entry alone proves a
     * Mockery chain — `allows`/`expects` with a method name are Double verbs too, so those still need a
     * Mockery-only link further down. `final` marks an entry Mockery returns the mock from, so nothing may
     * follow it.
     *
     * @param non-empty-list<MethodCall> $segments
     * @return array{verb: 'allows'|'expects'|'received', methods: non-empty-list<array{0: Node\Expr, 1: Node\Expr|null}>, args: list<Arg>|null, count: array{0: string, 1: list<Arg>}|null, certain: bool, consumed: int, final: bool}|null
     */
    private function parseEntry(array $segments): ?array
    {
        $first = $segments[0];
        $name = $this->callName($first);
        $never = ['never', []];

        # Magic form: `$m->allows()->run($x)`, `$m->shouldHaveReceived()->run($x)`, … — the second call
        # names the method and carries its arguments (an empty list means "called with no arguments").
        $magicVerbs = ['allows', 'expects', 'shouldReceive', 'shouldNotReceive', 'shouldHaveReceived', 'shouldNotHaveReceived'];
        if ($first->args === [] && \in_array($name, $magicVerbs, true)) {
            $magic = $segments[1] ?? null;
            $method = $magic !== null ? $this->callName($magic) : null;
            $args = $magic !== null ? $this->plainArgs($magic->args) : null;
            if ($method === null || $args === null) {
                return null;
            }

            return [
                'verb' => match ($name) {
                    'allows', 'shouldReceive', 'shouldNotReceive' => 'allows',
                    'expects' => 'expects',
                    default => 'received',
                },
                'methods' => [[new String_($method), null]],
                'args' => $args,
                'count' => $name === 'shouldNotReceive' || $name === 'shouldNotHaveReceived' ? $never : null,
                'certain' => true,
                'consumed' => 2,
                'final' => false,
            ];
        }

        $simple = static fn(string $verb, array $methods, ?array $count, bool $certain, bool $final = false): array => [
            'verb' => $verb, 'methods' => $methods, 'args' => null, 'count' => $count, 'certain' => $certain, 'consumed' => 1, 'final' => $final,
        ];

        switch ($name) {
            case 'shouldReceive':
            case 'shouldNotReceive':
                $methods = $this->entryMethods($first->args, $name === 'shouldReceive');

                return $methods === null ? null : $simple('allows', $methods, $name === 'shouldNotReceive' ? $never : null, true);
            case 'allows':
                $arg = $this->singleArg($first->args);
                if ($arg instanceof Array_) {
                    # `allows(['run' => 1])` sets up each method and returns the mock, not an expectation.
                    $methods = $this->mapEntries($arg);

                    return $methods === null || $methods === [] ? null : $simple('allows', $methods, null, true, true);
                }
                // no break
            case 'expects':
                $methods = $this->entryMethods($first->args, false);

                return $methods === null || \count($first->args) !== 1 ? null : $simple($name, $methods, null, false);
            case 'shouldHaveReceived':
            case 'shouldNotHaveReceived':
                return $this->parseReceivedEntry($first, $name === 'shouldNotHaveReceived');
            case 'shouldHaveBeenCalled':
            case 'shouldNotHaveBeenCalled':
                return $this->parseInvokeEntry($first, $name === 'shouldNotHaveBeenCalled');
            default:
                return null;
        }
    }

    /**
     * The methods of a `shouldReceive(...)` / `shouldNotReceive(...)` / `allows('m')` entry: each name,
     * and — for `shouldReceive` — each `['m' => $return]` map. A demeter `'a->b'`, a computed map, a call
     * or a named argument has no Double form.
     *
     * @param list<Arg|VariadicPlaceholder> $args
     * @return non-empty-list<array{0: Node\Expr, 1: Node\Expr|null}>|null
     */
    private function entryMethods(array $args, bool $allowMaps): ?array
    {
        $methods = [];
        foreach ($args as $arg) {
            if (!$arg instanceof Arg || $arg->name !== null || $arg->unpack) {
                return null;
            }

            $value = $arg->value;
            if ($value instanceof Array_) {
                $entries = $allowMaps ? $this->mapEntries($value) : null;
                if ($entries === null) {
                    return null;
                }
                \array_push($methods, ...$entries);
                continue;
            }

            # A computed name converts only when it is certainly a string: Mockery reads an array as a map.
            if (!$value instanceof String_ && !$this->getType($value)->isString()->yes()) {
                return null;
            }

            if ($value instanceof String_ && \str_contains($value->value, '->')) {
                return null;
            }

            $methods[] = [$value, null];
        }

        return $methods === [] ? null : $methods;
    }

    /**
     * `shouldHaveReceived('m')` / `shouldHaveReceived('m', [$a, $b])` / `…('m', $closure)` and the
     * `shouldNotHaveReceived` twins.
     *
     * @return array{verb: 'received', methods: non-empty-list<array{0: Node\Expr, 1: null}>, args: list<Arg>|null, count: array{0: string, 1: list<Arg>}|null, certain: bool, consumed: int, final: bool}|null
     */
    private function parseReceivedEntry(MethodCall $entry, bool $never): ?array
    {
        $methods = \count($entry->args) <= 2 ? $this->entryMethods(\array_slice($entry->args, 0, 1), false) : null;
        if ($methods === null) {
            return null;
        }

        $args = null;
        $argList = $entry->args[1] ?? null;
        if ($argList !== null) {
            $args = $argList instanceof Arg ? $this->argumentList($argList->value) : null;
            if ($args === null) {
                return null;
            }
        }

        return [
            'verb' => 'received',
            'methods' => $methods,
            'args' => $args,
            'count' => $never ? ['never', []] : null,
            'certain' => true,
            'consumed' => 1,
            'final' => false,
        ];
    }

    /**
     * `shouldHaveBeenCalled()` / `shouldNotHaveBeenCalled($args)`: Mockery's verification of a mocked
     * callable, which is a call to `__invoke`.
     *
     * @return array{verb: 'received', methods: non-empty-list<array{0: Node\Expr, 1: null}>, args: list<Arg>|null, count: array{0: string, 1: list<Arg>}|null, certain: bool, consumed: int, final: bool}|null
     */
    private function parseInvokeEntry(MethodCall $entry, bool $never): ?array
    {
        $args = null;
        if ($entry->args !== []) {
            $list = $never && \count($entry->args) === 1 && $entry->args[0] instanceof Arg ? $entry->args[0]->value : null;
            $args = $list === null ? null : $this->argumentList($list);
            if ($args === null) {
                return null;
            }
        }

        return [
            'verb' => 'received',
            'methods' => [[new String_('__invoke'), null]],
            'args' => $args,
            'count' => $never ? ['never', []] : null,
            'certain' => true,
            'consumed' => 1,
            'final' => false,
        ];
    }

    /**
     * An argument list given as one value — `withArgs($list)`, `shouldHaveReceived('m', $list)` — as the
     * arguments of Double's `with()`: an array literal unpacks in place, an array-typed value spreads,
     * and a closure (or other callable) checks the whole list through `Argument::all()`.
     *
     * @return list<Arg>|null
     */
    private function argumentList(Node\Expr $list): ?array
    {
        if ($list instanceof Array_) {
            $items = $this->listItems($list);

            return $items === null ? null : \array_map(static fn(Node\Expr $item): Arg => new Arg($item), $items);
        }

        if ($list instanceof Closure || $list instanceof ArrowFunction) {
            return [new Arg($this->argument('all', [new Arg($list)]))];
        }

        $type = $this->getType($list);
        if ($type->isArray()->yes()) {
            return [new Arg($list, unpack: true)];
        }

        return $type->isCallable()->yes() ? [new Arg($this->argument('all', [new Arg($list)]))] : null;
    }

    /**
     * The `['method' => $return]` pairs of a literal map; null for a computed key, a spread or a list.
     *
     * @return list<array{0: String_, 1: Node\Expr}>|null
     */
    private function mapEntries(Array_ $map): ?array
    {
        $entries = [];
        foreach ($map->items as $item) {
            if ($item === null || !$item->key instanceof String_ || $item->unpack || $item->byRef) {
                return null;
            }
            $entries[] = [$item->key, $item->value];
        }

        return $entries;
    }

    /**
     * Folds the chain's remaining links into one Double chain per method. Links are emitted in source
     * order; a call count is emitted where its last link sits (`atLeast()->times(2)` is one count). A
     * chain that sets up several methods repeats every link on each, so it needs a root and link
     * arguments that are safe to evaluate once per method.
     *
     * @param array{verb: 'allows'|'expects'|'received', methods: non-empty-list<array{0: Node\Expr, 1: Node\Expr|null}>, args: list<Arg>|null, count: array{0: string, 1: list<Arg>}|null, certain: bool, consumed: int, final: bool} $entry
     * @param list<MethodCall> $links
     * @return non-empty-list<MethodCall>|null
     */
    private function rebuildLinks(Node\Expr $root, array $entry, array $links): ?array
    {
        if ($entry['final'] && $links !== []) {
            return null;
        }

        $isVerification = $entry['verb'] === 'received';
        $mockeryOnly = $entry['certain'];
        $tail = [];
        $count = $entry['count'];
        $countPosition = null;
        $bound = null;
        $byDefault = false;
        $narrowed = false;

        if ($entry['args'] !== null) {
            $with = $this->mapArgumentList($entry['args']);
            if ($with === null) {
                return null;
            }
            $tail[] = ['with', $with];
            $narrowed = true;
        }

        foreach ($links as $link) {
            $name = $this->callName($link);
            if ($name === null) {
                return null;
            }

            $mockeryOnly = $mockeryOnly || \in_array($name, self::MOCKERY_ONLY_LINKS, true);

            # `atLeast()` / `atMost()` only qualify the count that follows them.
            if (($name === 'atLeast' || $name === 'atMost') && $link->args === [] && $bound === null) {
                $bound = $name === 'atLeast' ? 'minimum' : 'maximum';
                continue;
            }

            $parsedCount = $this->parseCount($name, $link->args, $bound);
            if ($parsedCount !== false) {
                if ($parsedCount === null || $count !== null && $count[0] !== 'allows') {
                    return null;
                }

                $count = $parsedCount;
                $countPosition = \count($tail);
                $bound = null;
                continue;
            }

            if ($bound !== null) {
                return null;
            }

            if ($name === 'byDefault' && !$isVerification) {
                if ($link->args !== []) {
                    return null;
                }
                $byDefault = true;
                continue;
            }

            $mapped = $isVerification
                ? $this->mapVerificationLink($name, $link->args)
                : $this->mapExpectationLink($name, $link->args, $root);
            if ($mapped === false) {
                return null;
            }

            if ($mapped !== null) {
                $narrowed = $narrowed || $mapped[0] === 'with';
                $tail[] = $mapped;
            }
        }

        if ($bound !== null || !$mockeryOnly) {
            return null;
        }

        # A default expectation is overridable only while it is the least specific one Double could pick.
        if ($byDefault && ($narrowed || $count !== null)) {
            return null;
        }

        [$verb, $countCall] = $this->resolveVerb($entry['verb'], $count);
        if ($countCall !== null) {
            \array_splice($tail, $countPosition ?? \count($tail), 0, [$countCall]);
        }

        $methods = $entry['methods'];
        if (\count($methods) > 1 && ($this->cloneRoot($root) === null || !$this->isRepeatable($tail))) {
            return null;
        }

        $chains = [];
        foreach ($methods as $index => [$method, $return]) {
            $chainRoot = $index === 0 ? $root : $this->cloneRoot($root);
            \assert($chainRoot !== null);

            $result = $this->rebuilt(new MethodCall($chainRoot, new Identifier($verb), [new Arg($method)]));
            if ($return !== null) {
                $result = $this->rebuilt(new MethodCall($result, new Identifier('returns'), [new Arg($return)]));
            }

            foreach ($tail as [$call, $args]) {
                $result = $this->rebuilt(new MethodCall($result, new Identifier($call), $index === 0 ? $args : $this->copyArgs($args)));
            }

            $chains[] = $result;
        }

        return $chains;
    }

    /**
     * Whether the tail's arguments can be evaluated once per method without changing what the test does:
     * values, variables, constants and closures, and the pure `Argument::*` matcher calls — nothing that
     * constructs, calls or assigns.
     *
     * @param list<array{0: string, 1: list<Arg|VariadicPlaceholder>}> $tail
     */
    private function isRepeatable(array $tail): bool
    {
        foreach ($tail as [, $args]) {
            foreach ($args as $arg) {
                if ($arg instanceof Arg && !$this->isPureExpr($arg->value)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function isPureExpr(Node\Expr $expr): bool
    {
        return match (true) {
            $expr instanceof Scalar, $expr instanceof ConstFetch, $expr instanceof ClassConstFetch, $expr instanceof Variable,
            $expr instanceof Closure, $expr instanceof ArrowFunction => true,
            $expr instanceof PropertyFetch => $this->isPureExpr($expr->var),
            $expr instanceof UnaryMinus => $this->isPureExpr($expr->expr),
            $expr instanceof Array_ => \array_reduce(
                $expr->items,
                fn(bool $pure, $item): bool => $pure && $item !== null && $this->isPureExpr($item->value) && ($item->key === null || $this->isPureExpr($item->key)),
                true,
            ),
            $expr instanceof StaticCall => $this->isArgumentCall($expr) && $this->allPure($expr->args),
            $expr instanceof MethodCall => $this->isArgumentCall($expr->var) && $this->allPure($expr->args),
            default => false,
        };
    }

    /**
     * @param list<Arg|VariadicPlaceholder> $args
     */
    private function allPure(array $args): bool
    {
        foreach ($args as $arg) {
            if (!$arg instanceof Arg || !$this->isPureExpr($arg->value)) {
                return false;
            }
        }

        return true;
    }

    private function isArgumentCall(Node\Expr $expr): bool
    {
        return $expr instanceof StaticCall && $this->isName($expr->class, 'JMac\\Testing\\Matching\\Argument');
    }

    /**
     * @param list<Arg|VariadicPlaceholder> $args
     * @return list<Arg|VariadicPlaceholder>
     */
    private function copyArgs(array $args): array
    {
        return \array_map(
            static fn(Arg|VariadicPlaceholder $arg): Arg|VariadicPlaceholder => $arg instanceof Arg
                ? new Arg(PhpunitConstraint::copy($arg->value), $arg->byRef, $arg->unpack, name: $arg->name)
                : $arg,
            $args,
        );
    }

    /**
     * A call-count link as `[kind, args]` — `exact`, `minimum`, `maximum`, `range`, `never` or `allows`
     * (zero or more) — or false when the link is not a count at all, and null when it is a count with no
     * Double form (a `between()` under `atLeast()`, named or spread arguments).
     *
     * @param list<Arg|VariadicPlaceholder> $args
     * @return array{0: string, 1: list<Arg>}|false|null
     */
    private function parseCount(string $name, array $args, ?string $bound): array|false|null
    {
        $positional = $this->positionalValues($args);

        switch ($name) {
            case 'never':
            case 'zeroOrMoreTimes':
                return $args === [] && $bound === null ? [$name === 'never' ? 'never' : 'allows', []] : null;
            case 'between':
                return $bound === null && $positional !== null && \count($positional) === 2
                    ? ['range', [new Arg($positional[0]), new Arg($positional[1])]]
                    : null;
            case 'once':
            case 'twice':
                $value = $args === [] ? new Int_($name === 'once' ? 1 : 2) : null;
                break;
            case 'times':
                $value = $positional !== null && \count($positional) === 1 ? $positional[0] : null;
                break;
            default:
                return false;
        }

        if ($value === null) {
            return null;
        }

        return [$bound ?? 'exact', [$bound === null ? new Arg($value) : new Arg($value, name: new Identifier($bound))]];
    }

    /**
     * The argument values of a call when every argument is plain and positional, null otherwise.
     *
     * @param list<Arg|VariadicPlaceholder> $args
     * @return list<Node\Expr>|null
     */
    private function positionalValues(array $args): ?array
    {
        $values = [];
        foreach ($args as $arg) {
            if (!$arg instanceof Arg || $arg->name !== null || $arg->unpack) {
                return null;
            }
            $values[] = $arg->value;
        }

        return $values;
    }

    /**
     * @param list<Arg|VariadicPlaceholder> $args
     * @return list<Arg>|null
     */
    private function plainArgs(array $args): ?array
    {
        $values = $this->positionalValues($args);

        return $values === null ? null : \array_map(static fn(Node\Expr $value): Arg => new Arg($value), $values);
    }

    /**
     * The Double entry verb and trailing count call for a parsed count: an expectation with no count stays
     * `allows()` (Mockery's `shouldReceive()`/`allows()` default is zero or more), any count makes it
     * `expects()` — whose own default is exactly once, so `once()` needs no `times()`. A verification keeps
     * `received()`, whose default is at least once.
     *
     * @param array{0: string, 1: list<Arg>}|null $count
     * @return array{0: string, 1: array{0: string, 1: list<Arg>}|null}
     */
    private function resolveVerb(string $entryVerb, ?array $count): array
    {
        if ($entryVerb === 'received') {
            return ['received', $this->countCall($count)];
        }

        if ($count === null) {
            return [$entryVerb, null];
        }

        if ($count[0] === 'allows') {
            return ['allows', null];
        }

        $isOnce = $count[0] === 'exact' && $count[1][0]->value instanceof Int_ && $count[1][0]->value->value === 1;

        return ['expects', $isOnce ? null : $this->countCall($count)];
    }

    /**
     * @param array{0: string, 1: list<Arg>}|null $count
     * @return array{0: string, 1: list<Arg>}|null
     */
    private function countCall(?array $count): ?array
    {
        return match ($count[0] ?? null) {
            null, 'allows' => null,
            'never' => ['never', []],
            default => ['times', $count[1]],
        };
    }

    /**
     * Maps an expectation link to `[method, args]`, null for a link that drops away, or false when it has
     * no faithful Double form.
     *
     * @param list<Arg|VariadicPlaceholder> $args
     * @return array{0: string, 1: list<Arg|VariadicPlaceholder>}|false|null
     */
    private function mapExpectationLink(string $name, array $args, Node\Expr $root): array|false|null
    {
        return match ($name) {
            'with', 'withArgs', 'withNoArgs', 'withAnyArgs', 'withSomeOfArgs' => $this->mapArgumentLink($name, $args),
            'andReturn', 'andReturns' => ['returns', $args === [] ? [new Arg($this->literal('null'))] : $args],
            'andReturnValues' => $this->mapSpreadList('returns', $args),
            'andReturnNull' => $args === [] ? ['returns', [new Arg($this->literal('null'))]] : false,
            'andReturnTrue' => $args === [] ? ['returns', [new Arg($this->literal('true'))]] : false,
            'andReturnFalse' => $args === [] ? ['returns', [new Arg($this->literal('false'))]] : false,
            'andReturnSelf' => $args === [] ? $this->returnSelf($root) : false,
            'andReturnArg' => $this->mapReturnArg($args),
            'andReturnUsing' => \count($args) === 1 ? ['resolves', $args] : false,
            'andThrow', 'andThrows' => $this->mapThrow($args),
            'andThrowExceptions' => $this->mapSpreadList('throws', $args),
            'ordered' => $args === [] ? ['ordered', []] : false,
            default => false,
        };
    }

    /**
     * A verification chain only narrows arguments and counts; a return verb on it has no meaning.
     *
     * @param list<Arg|VariadicPlaceholder> $args
     * @return array{0: string, 1: list<Arg|VariadicPlaceholder>}|false|null
     */
    private function mapVerificationLink(string $name, array $args): array|false|null
    {
        return match ($name) {
            'with', 'withArgs', 'withNoArgs', 'withAnyArgs', 'withSomeOfArgs' => $this->mapArgumentLink($name, $args),
            default => false,
        };
    }

    /**
     * `with(...)` keeps its values (each matcher mapped), `withArgs($list)` goes through
     * {@see argumentList()}, `withSomeOfArgs(...)` → a strict `in_array()` check per value over the whole
     * argument list, `withNoArgs()` → `with(Argument::none())`, `withAnyArgs()` drops.
     *
     * @param list<Arg|VariadicPlaceholder> $args
     * @return array{0: string, 1: list<Arg|VariadicPlaceholder>}|false|null
     */
    private function mapArgumentLink(string $name, array $args): array|false|null
    {
        if ($name === 'withAnyArgs') {
            return $args === [] ? null : false;
        }

        if ($name === 'withNoArgs') {
            return $args === [] ? ['with', [new Arg($this->argument('none'))]] : false;
        }

        if ($name === 'withSomeOfArgs') {
            $all = $this->someOfArgs($args);

            return $all === null ? false : ['with', [new Arg($all)]];
        }

        if ($name === 'withArgs') {
            $list = \count($args) === 1 && $args[0] instanceof Arg ? $args[0]->value : null;
            $listArgs = $list === null ? null : $this->argumentList($list);
            if ($listArgs === null) {
                return false;
            }

            # An argument list checked by a closure, or spread at run time, has no matchers to map.
            if (!$list instanceof Array_) {
                return ['with', $listArgs];
            }

            $args = $listArgs;
        }

        $plain = $this->plainArgs($args);
        $mapped = $plain === null ? null : $this->mapArgumentList($plain);

        return $mapped === null ? false : ['with', $mapped];
    }

    /**
     * `withSomeOfArgs($a, $b)` → `Argument::all(fn (...$args) => in_array($a, $args, true) && …)`, the
     * check Mockery runs. A value reading a variable named `$args` would be shadowed by the parameter.
     *
     * @param list<Arg|VariadicPlaceholder> $args
     */
    private function someOfArgs(array $args): ?StaticCall
    {
        $values = $this->positionalValues($args);
        if ($values === null || $values === []) {
            return null;
        }

        $check = null;
        foreach ($values as $value) {
            if ($this->isMockeryMatcher($value) || $this->readsVariable($value, 'args')) {
                return null;
            }

            $inArray = new FuncCall(new Name('in_array'), [new Arg($value), new Arg(new Variable('args')), new Arg($this->literal('true'))]);
            $check = $check === null ? $inArray : new BinaryOp\BooleanAnd($check, $inArray);
        }

        return $this->argument('all', [new Arg(new ArrowFunction([
            'params' => [new Param(new Variable('args'), variadic: true)],
            'expr' => $check,
        ]))]);
    }

    /**
     * @param list<Arg> $args
     * @return list<Arg>|null
     */
    private function mapArgumentList(array $args): ?array
    {
        # The magic `allows()->m()` form with no arguments means "called with none".
        if ($args === []) {
            return [new Arg($this->argument('none'))];
        }

        $mapped = [];
        foreach ($args as $arg) {
            if ($arg->unpack) {
                $mapped[] = $arg;
                continue;
            }

            $matcher = $this->mapMatcher($arg->value);
            if ($matcher === null) {
                return null;
            }

            $mapped[] = new Arg($matcher);
        }

        return $mapped;
    }

    /**
     * Maps one argument expectation: a plain value passes through, a `Mockery::*` matcher becomes its
     * `Argument::*` counterpart (or a bare value, or a predicate), and a matcher with no faithful form
     * returns null to abort the chain. A Hamcrest matcher (a global function call) aborts too.
     */
    private function mapMatcher(Node\Expr $value): ?Node\Expr
    {
        if ($value instanceof FuncCall && $value->name instanceof Name && $this->isHamcrestMatcher($value->name->toString())) {
            return null;
        }

        if (!$this->isMockeryMatcher($value)) {
            return $value;
        }

        \assert($value instanceof StaticCall);
        $name = $this->callName($value);
        $values = $this->positionalValues($value->args);
        if ($values === null) {
            return null;
        }

        $args = [];
        foreach ($values as $argValue) {
            # A matcher nested in another (`Mockery::not(Mockery::type(...))`) is compared as a plain
            # object by Mockery and rejected outright by Double.
            if ($this->isMockeryMatcher($argValue)) {
                return null;
            }
            $args[] = new Arg($argValue);
        }
        $first = $values[0] ?? null;
        $single = \count($args) === 1;

        return match ($name) {
            'any' => $args === [] ? $this->argument('any') : null,
            'type' => $single ? $this->typeMatcher($first) : null,
            'on' => $single ? $this->argument('satisfies', $args) : null,
            'capture' => $single ? $this->argument('capture', $args) : null,
            'pattern' => $single ? $this->argument('matches', $args) : null,
            'anyOf' => $args !== [] ? $this->argument('any', $args) : null,
            'notAnyOf' => $args !== [] ? new MethodCall($this->argument('not'), new Identifier('any'), $args) : null,
            'not' => $single ? $this->argument('not', $args) : null,
            'isSame' => $single ? $this->argument('same', $args) : null,
            'mustBe', 'isEqual' => $single ? $first : null,
            'hasValue' => $single ? $this->argument('contains', $args) : null,
            'contains' => match (true) {
                $single => $this->argument('contains', $args),
                $args !== [] => $this->containsAll($values),
                default => null,
            },
            'hasKey' => $single ? $this->hasKeyPredicate($first) : null,
            'subset' => $this->subsetPredicate($values),
            'ducktype' => $args !== [] ? $this->ducktypePredicate($values) : null,
            'andAnyOtherArgs', 'andAnyOthers' => $args === [] ? $this->argument('remaining') : null,
            default => null,
        };
    }

    /**
     * `Mockery::type('integer')` → `Argument::type('int')`. Mockery checks a name through `is_<name>()`
     * when that function exists, else as a class: the names Double spells differently are normalised,
     * the `is_*()` checks Double has no name for become a predicate, and a class name passes through.
     */
    private function typeMatcher(?Node\Expr $type): ?Node\Expr
    {
        if (!$type instanceof String_) {
            return $type === null ? null : $this->argument('type', [new Arg($type)]);
        }

        $lower = \strtolower($type->value);
        $name = self::TYPE_ALIASES[$lower] ?? $lower;

        if (\in_array($name, self::DOUBLE_TYPES, true)) {
            return $this->argument('type', [new Arg(new String_($name))]);
        }

        if (\in_array($name, self::PREDICATE_TYPES, true)) {
            return $this->satisfies($this->func('is_' . $name, [$this->value()]));
        }

        return $this->argument('type', [new Arg($type)]);
    }

    /**
     * `Mockery::hasKey($k)` → `(is_array($value) || $value instanceof \ArrayAccess) && array_key_exists($k, (array) $value)`,
     * as Mockery's `HasKey` checks it.
     */
    private function hasKeyPredicate(?Node\Expr $key): ?StaticCall
    {
        if ($key === null) {
            return null;
        }

        return $this->satisfies(new BinaryOp\BooleanAnd(
            new BinaryOp\BooleanOr(
                $this->func('is_array', [$this->value()]),
                new Instanceof_($this->value(), new FullyQualified('ArrayAccess')),
            ),
            $this->func('array_key_exists', [$key, new Cast\Array_($this->value())]),
        ));
    }

    /**
     * `Mockery::contains($a, $b)` → every value is in the array, compared loosely as Mockery does.
     *
     * @param list<Node\Expr> $values
     */
    private function containsAll(array $values): StaticCall
    {
        $check = $this->func('is_array', [$this->value()]);
        foreach ($values as $value) {
            $check = new BinaryOp\BooleanAnd($check, $this->func('in_array', [$value, $this->value()]));
        }

        return $this->satisfies($check);
    }

    /**
     * `Mockery::subset($part, $strict)` → `is_array($value) && array_replace_recursive($value, $part) === $value`,
     * Mockery's own check (`==` when not strict). The flag must be a literal.
     *
     * @param list<Node\Expr> $values
     */
    private function subsetPredicate(array $values): ?StaticCall
    {
        $part = $values[0] ?? null;
        $strict = $values[1] ?? $this->literal('true');
        if ($part === null || \count($values) > 2 || !$strict instanceof ConstFetch) {
            return null;
        }

        $flag = \strtolower($strict->name->toString());
        if ($flag !== 'true' && $flag !== 'false') {
            return null;
        }

        $replaced = $this->func('array_replace_recursive', [$this->value(), $part]);
        $comparison = $flag === 'true'
            ? new BinaryOp\Identical($replaced, $this->value())
            : new BinaryOp\Equal($replaced, $this->value());

        return $this->satisfies(new BinaryOp\BooleanAnd($this->func('is_array', [$this->value()]), $comparison));
    }

    /**
     * `Mockery::ducktype('a', 'b')` → an object that has each of those methods.
     *
     * @param list<Node\Expr> $methods
     */
    private function ducktypePredicate(array $methods): StaticCall
    {
        $check = $this->func('is_object', [$this->value()]);
        foreach ($methods as $method) {
            $check = new BinaryOp\BooleanAnd($check, $this->func('method_exists', [$this->value(), $method]));
        }

        return $this->satisfies($check);
    }

    private function isHamcrestMatcher(string $function): bool
    {
        return \in_array(\strtolower(\ltrim($function, '\\')), [
            'anything', 'equalto', 'identicalto', 'typeof', 'aninstanceof', 'containsstring', 'hasitem',
            'hasentry', 'haskey', 'hasvalue', 'greaterthan', 'lessthan', 'stringstartswith', 'stringendswith',
            'matchespattern', 'nullvalue', 'notnullvalue', 'not', 'anyof', 'allof', 'is', 'isnonemptystring',
            'emptyarray', 'arraywithsize', 'closeto',
        ], true);
    }

    /**
     * `andReturnValues([$a, $b])` → `returns($a, $b)`, and a computed list spreads: `returns(...$list)`.
     * The same for `andThrowExceptions()` → `throws()`.
     *
     * @param list<Arg|VariadicPlaceholder> $args
     * @return array{0: string, 1: list<Arg>}|false
     */
    private function mapSpreadList(string $verb, array $args): array|false
    {
        $list = \count($args) === 1 && $args[0] instanceof Arg && !$args[0]->unpack ? $args[0]->value : null;
        if ($list === null) {
            return false;
        }

        if ($list instanceof Array_) {
            $items = $this->listItems($list);

            return $items === null || $items === [] ? false : [$verb, \array_map(static fn(Node\Expr $item): Arg => new Arg($item), $items)];
        }

        return $this->getType($list)->isArray()->yes() ? [$verb, [new Arg($list, unpack: true)]] : false;
    }

    /**
     * `andReturnArg($n)` → `resolves(fn (...$args) => $args[$n])`.
     *
     * @param list<Arg|VariadicPlaceholder> $args
     * @return array{0: string, 1: list<Arg>}|false
     */
    private function mapReturnArg(array $args): array|false
    {
        if (\count($args) !== 1 || !$args[0] instanceof Arg) {
            return false;
        }

        $resolver = new ArrowFunction([
            'params' => [new Param(new Variable('args'), null, null, false, true)],
            'expr' => new ArrayDimFetch(new Variable('args'), $args[0]->value),
        ]);

        return ['resolves', [new Arg($resolver)]];
    }

    /**
     * `andThrow($e)` → `throws($e)`; Mockery ignores a message or code passed next to an exception
     * object, so literal ones drop. The class-string form `andThrow(X::class, $message, $code)` builds the
     * exception Mockery would have built, `throws(new X($message, $code))`.
     *
     * @param list<Arg|VariadicPlaceholder> $args
     * @return array{0: string, 1: list<Arg>}|false
     */
    private function mapThrow(array $args): array|false
    {
        $values = $this->positionalValues($args);
        if ($values === null || $values === [] || \count($values) > 4) {
            return false;
        }

        $exception = \array_shift($values);
        $class = match (true) {
            $exception instanceof ClassConstFetch && $this->isName($exception->name, 'class') && $exception->class instanceof Name => $exception->class,
            $exception instanceof String_ => new FullyQualified(\ltrim($exception->value, '\\')),
            default => null,
        };

        if ($class !== null) {
            return ['throws', [new Arg(new New_($class, \array_map(static fn(Node\Expr $value): Arg => new Arg($value), $values)))]];
        }

        foreach ($values as $ignored) {
            if (!$ignored instanceof Scalar && !$ignored instanceof ConstFetch) {
                return false;
            }
        }

        return ['throws', [new Arg($exception)]];
    }

    /**
     * `andReturnSelf()` → `returns(<the double>)`: only a local variable or a `$this->prop` root can be
     * repeated safely; anything else aborts.
     *
     * @return array{0: string, 1: list<Arg>}|false
     */
    private function returnSelf(Node\Expr $root): array|false
    {
        $self = $this->cloneRoot($root);

        return $self === null ? false : ['returns', [new Arg($self)]];
    }

    /**
     * A fresh copy of the double's root: a local variable or a `$this->prop` property. Null for anything
     * else, which cannot be repeated without evaluating it again.
     */
    private function cloneRoot(Node\Expr $root): ?Node\Expr
    {
        if ($root instanceof Variable && \is_string($root->name)) {
            return new Variable($root->name);
        }

        if (
            $root instanceof PropertyFetch
            && $root->var instanceof Variable
            && \is_string($root->var->name)
            && $root->name instanceof Identifier
        ) {
            return new PropertyFetch(new Variable($root->var->name), new Identifier($root->name->toString()));
        }

        return null;
    }

    /**
     * The values of a plain list literal, or null when the expression is not one (keys, spreads or holes
     * would change what the call receives).
     *
     * @return list<Node\Expr>|null
     */
    private function listItems(Node\Expr $value): ?array
    {
        if (!$value instanceof Array_) {
            return null;
        }

        $items = [];
        foreach ($value->items as $item) {
            if ($item === null || $item->key !== null || $item->unpack || $item->byRef) {
                return null;
            }

            $items[] = $item->value;
        }

        return $items;
    }

    /**
     * Pins the Mockery factory an unconvertible chain roots on — `Mockery::mock(X)->shouldReceive(...)`
     * used inline — so it stays Mockery.
     */
    private function pinFactory(Node\Expr $root): void
    {
        if ($root instanceof StaticCall && $this->factoryKind($root) !== null) {
            $root->setAttribute(self::KEEP, true);
        }
    }

    private function rebuilt(MethodCall $call): MethodCall
    {
        $call->setAttribute(self::REBUILT, true);

        return $call;
    }

    private function isMockeryCall(StaticCall $node, string $method): bool
    {
        return $this->isName($node->class, 'Mockery') && $this->isName($node->name, $method);
    }

    private function isMockeryMatcher(Node\Expr $value): bool
    {
        return $value instanceof StaticCall && $this->isName($value->class, 'Mockery');
    }

    private function readsVariable(Node\Expr $expr, string $name): bool
    {
        foreach ((new NodeFinder())->findInstanceOf($expr, Variable::class) as $variable) {
            if ($variable->name === $name) {
                return true;
            }
        }

        return false;
    }

    private function singleArg(array $args): ?Node\Expr
    {
        return \count($args) === 1 && $args[0] instanceof Arg && $args[0]->name === null && !$args[0]->unpack ? $args[0]->value : null;
    }

    private function callName(MethodCall|StaticCall $call): ?string
    {
        return $call->name instanceof Identifier ? $call->name->toString() : null;
    }

    private function satisfies(Node\Expr $predicate): StaticCall
    {
        return $this->argument('satisfies', [new Arg(new ArrowFunction([
            'params' => [new Param($this->value())],
            'expr' => $predicate,
        ]))]);
    }

    private function value(): Variable
    {
        return new Variable($this->predicateName);
    }

    private function literal(string $name): ConstFetch
    {
        return new ConstFetch(new Name($name));
    }

    /**
     * @param list<Node\Expr> $args
     */
    private function func(string $name, array $args): FuncCall
    {
        return new FuncCall(new Name($name), \array_map(static fn(Node\Expr $arg): Arg => new Arg($arg), $args));
    }

    /**
     * @param list<Arg> $args
     */
    private function argument(string $method, array $args = []): StaticCall
    {
        return new StaticCall(new FullyQualified('JMac\\Testing\\Matching\\Argument'), new Identifier($method), $args);
    }

    /**
     * @param list<Arg> $args
     */
    private function doubleFor(array $args): StaticCall
    {
        return new StaticCall(new FullyQualified('JMac\\Testing\\Double'), new Identifier('for'), $args);
    }
}
