<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToDouble;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\VariadicPlaceholder;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Internal\PhpunitConstraint;
use Testo\Bridge\Rector\Internal\PhpunitMockFactory;
use Testo\Bridge\Rector\Internal\PredicateVariable;
use Testo\Bridge\Rector\Internal\ReturnValueMap;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;
use Testo\Codecov\Covers;

/**
 * Converts a PHPUnit mock/stub — creation and its configuration chain — into the equivalent
 * {@see \JMac\Testing\Double} form (the `testo/bridge-double` package):
 *
 *     $dep = $this->createMock(Dependency::class);
 *     $dep->expects($this->once())->method('run')->with('x')->willReturn('y');
 *     // becomes
 *     $dep = \JMac\Testing\Double::for(Dependency::class);
 *     $dep->expects('run')->times(1)->with('x')->returns('y');
 *
 * Creation ({@see PhpunitMockFactory} reads the PHPUnit side): `createMock(X)`/`createStub(X)`, the
 * intersection factories and the constructor-disabling builder → `Double::for(...)`, which never runs a
 * constructor and answers an unconfigured call with a default, as PHPUnit does;
 * `disableAutoReturnValueGeneration()` adds `->strict()`. A partial double — `createPartialMock(X, ['a'])`,
 * `onlyMethods(['a'])` — is `Double::for(X)->passthru()` (with `passthru(new X(...))` when the builder runs
 * the constructor) plus an `allows('a')` per doubled method, so those answer with a default while every
 * other method runs for real. `createConfiguredMock(X, ['m' => $v])` is `Double::for(X)` plus an
 * `allows('m')->returns($v)` per entry. The added `allows()` calls are statements of their own, so these
 * two forms convert when the double is assigned to a variable or property.
 *
 * Configuration chain, rebuilt from its outermost call: PHPUnit's invocation matcher moves off
 * `expects()` onto the verb — `any()` picks `allows()`, every other matcher keeps `expects()` and folds
 * into `times()`/`never()` (`once` → `times(1)`, `exactly($n)` → `times($n)`, `atLeastOnce` →
 * `times(minimum: 1)`, `atLeast`/`atMost` → `times(minimum:/maximum:)`); the method name moves from
 * `->method('m')` onto the verb; `withAnyParameters()` drops away; the returns map
 * `willReturn`/`willReturnOnConsecutiveCalls` → `returns`, `willThrowException` → `throws`,
 * `willReturnCallback` → `resolves`, `willReturnArgument($n)` → `resolves(fn (...$args) => $args[$n])`,
 * `willReturnSelf()` → `returns(<the double>)`, `willReturnMap($map)` → `resolves(<the map lookup>)`
 * ({@see ReturnValueMap}), plus the legacy `will($this->returnValue()/…/returnValueMap())` wrappers.
 *
 * `with()` constraints become `Argument::*` matchers where Double has one: `anything` → `any`,
 * `identicalTo` → `same`, `isInstanceOf` and the `isType()` names Double's `type()` knows → `type`,
 * `callback` → `satisfies`, `contains`/`containsEqual` → `contains`, `containsIdentical` →
 * `contains(Argument::same(...))`, `matchesRegularExpression` → `matches`, `logicalNot` → `not`,
 * `logicalOr` → `any(...)`; `equalTo($x)` unwraps to `$x` and `isNull`/`isTrue`/`isFalse` to literals.
 * Every other constraint becomes `Argument::satisfies(fn ($value) => …)` over its own PHP expression
 * ({@see PhpunitConstraint}) — comparisons, `logicalAnd`/`logicalXor`, delta/case/canonicalizing
 * equality, string, count, JSON, file and type checks.
 *
 * Conservative by design: a chain is rewritten only when it carries a PHPUnit mock signal — an
 * `expects()` with a recognised matcher, or one of the `will*` return verbs — so an unrelated fluent
 * chain is left alone, and any link with no faithful counterpart aborts the whole chain: a variable
 * matcher, `prophesize()`, `getMockForAbstractClass()`, a builder step with no Double form, a strict
 * partial, `stringContains()` with a computed case flag. Leaving a raw `$this->…()` constraint would
 * break once the test loses its TestCase base. Those stay for manual migration (see
 * {@see UnconvertibleMockToDoubleRector} and TODO.md).
 */
#[TestRectorFixtures('CreateMockToDoubleRector')]
#[Covers(self::class)]
#[Covers(PhpunitConstraint::class)]
#[Covers(PhpunitMockFactory::class)]
#[Covers(PredicateVariable::class)]
#[Covers(ReturnValueMap::class)]
final class CreateMockToDoubleRector extends AbstractRector
{
    /**
     * The type names Double's `Argument::type()` checks natively; any other name it treats as a class.
     */
    private const DOUBLE_TYPES = ['int', 'float', 'string', 'bool', 'array', 'object', 'callable', 'iterable', 'null'];

    /**
     * The variable the predicates of the statement being rebuilt are written over.
     */
    private string $predicateName = 'value';

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert PHPUnit `createMock()`/`createStub()` and their `expects()/method()/will*()` configuration chains into `\JMac\Testing\Double` calls',
            [
                new CodeSample(
                    <<<'PHP'
                        $dep = $this->createMock(Dependency::class);
                        $dep->expects($this->once())->method('run')->with('x')->willReturn('y');
                        PHP,
                    <<<'PHP'
                        $dep = \JMac\Testing\Double::for(Dependency::class);
                        $dep->expects('run')->times(1)->with('x')->returns('y');
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
        return [Expression::class, MethodCall::class];
    }

    /**
     * The chain rebuild runs at statement level and the mock-factory rewrite at call level, so the two
     * never interfere: an unconvertible outer link (e.g. `willReturnSelf()` on a complex root) leaves the
     * whole statement alone instead of the inner `expects()->method()` being rewritten on its own. The
     * factories that need statements of their own are expanded at statement level too.
     *
     * @param Expression|MethodCall $node
     * @return Node|list<Expression>|null
     */
    #[\Override]
    public function refactor(Node $node): Node|array|null
    {
        if ($node instanceof MethodCall) {
            $factory = PhpunitMockFactory::parse($node);
            $double = $factory === null ? null : $this->doubleFor($factory);

            return $double === null || $double['setup'] !== [] ? null : $double['double'];
        }

        if ($node->expr instanceof Assign) {
            return $this->expandFactoryAssignment($node->expr);
        }

        if (!$node->expr instanceof MethodCall) {
            return null;
        }

        $this->predicateName = PredicateVariable::nameFor($node);
        $rebuilt = $this->rebuildMockChain($node->expr);
        if ($rebuilt === null) {
            return null;
        }

        $node->expr = $rebuilt;

        return $node;
    }

    /**
     * `$dep = $this->createPartialMock(X, ['a'])` → `$dep = Double::for(X)->passthru();` followed by
     * `$dep->allows('a');` — the same for `createConfiguredMock()` with `allows('m')->returns($v)`.
     *
     * @return list<Expression>|null
     */
    private function expandFactoryAssignment(Assign $assign): ?array
    {
        if (!$assign->expr instanceof MethodCall) {
            return null;
        }

        $factory = PhpunitMockFactory::parse($assign->expr);
        $double = $factory === null ? null : $this->doubleFor($factory);
        if ($double === null || $double['setup'] === [] || $this->cloneDoubleRoot($assign->var) === null) {
            return null;
        }

        $statements = [new Expression(new Assign($assign->var, $double['double']))];
        foreach ($double['setup'] as [$method, $return]) {
            $root = $this->cloneDoubleRoot($assign->var);
            \assert($root !== null);

            $call = new MethodCall($root, new Identifier('allows'), [new Arg($method)]);
            if ($return !== null) {
                $call = new MethodCall($call, new Identifier('returns'), [new Arg($return)]);
            }

            $statements[] = new Expression($call);
        }

        return $statements;
    }

    /**
     * The Double for a PHPUnit factory: the creation expression, plus the `[method, return]` pairs that
     * still have to be set up on it (`allows(method)`, with `returns(return)` when one is given). Null
     * when the factory has no Double form.
     *
     * @return array{double: Node\Expr, setup: list<array{0: Node\Expr, 1: Node\Expr|null}>}|null
     */
    private function doubleFor(PhpunitMockFactory $factory): ?array
    {
        if ($factory->configuration !== null) {
            $setup = [];
            foreach ($factory->configuration->items as $item) {
                \assert($item !== null && $item->key !== null);
                $setup[] = [$item->key, $item->value];
            }

            return ['double' => $this->doubleCall($factory->targets), 'setup' => $setup];
        }

        if ($factory->partialMethods === null) {
            $double = $this->doubleCall($factory->targets);

            return ['double' => $factory->autoReturn ? $double : new MethodCall($double, new Identifier('strict')), 'setup' => []];
        }

        # A doubled method without auto-return fails when called unconfigured; a passthru double has no
        # per-method strictness to express that with.
        if (!$factory->autoReturn && $factory->partialMethods !== []) {
            return null;
        }

        $real = $factory->constructorArgs === null ? [] : [new Arg($this->construct($factory->target(), $factory->constructorArgs))];

        return [
            'double' => new MethodCall($this->doubleCall([new Arg($factory->target())]), new Identifier('passthru'), $real),
            'setup' => \array_map(static fn(Node\Expr $method): array => [$method, null], $factory->partialMethods),
        ];
    }

    /**
     * `new X(...$constructorArgs)` — the real instance a passthru double copies its state from, built the
     * way PHPUnit's builder would have run the constructor. A literal argument list is unpacked in place.
     */
    private function construct(Node\Expr $class, Node\Expr $constructorArgs): New_
    {
        $className = match (true) {
            $class instanceof ClassConstFetch && $this->isName($class->name, 'class') && $class->class instanceof Name => $class->class,
            $class instanceof String_ => new FullyQualified(\ltrim($class->value, '\\')),
            default => $class,
        };

        $args = [new Arg($constructorArgs, unpack: true)];
        if ($constructorArgs instanceof Array_) {
            $args = [];
            foreach ($constructorArgs->items as $item) {
                if ($item === null || $item->key !== null || $item->unpack || $item->byRef) {
                    $args = [new Arg($constructorArgs, unpack: true)];
                    break;
                }
                $args[] = new Arg($item->value);
            }
        }

        return new New_($className, $args);
    }

    /**
     * Rebuilds a configuration chain into its Double form, or returns null when the chain carries no
     * PHPUnit mock signal or hits a link with no faithful counterpart.
     *
     * Called with the statement's whole expression, so the entire chain is converted in one shot. The
     * result is idempotent: a second pass sees `expects('m')` (a string argument where a matcher used
     * to be) and the Double return verbs, none of which re-trigger a rewrite.
     */
    private function rebuildMockChain(MethodCall $node): ?MethodCall
    {
        # The chain root may itself be a call: a PHPUnit factory (`$this->createMock(X)->method(…)`) or the
        # Double it has already become (`Double::for(X)->passthru()`). Either one ends the chain.
        $segments = [];
        $cursor = $node;
        while ($cursor instanceof MethodCall && !$this->isFactoryRoot($cursor)) {
            $segments[] = $cursor;
            $cursor = $cursor->var;
        }
        if ($segments === []) {
            return null;
        }
        $segments = \array_reverse($segments);

        $root = $segments[0]->var;
        $result = $root;
        $isMock = false;
        $count = \count($segments);

        for ($i = 0; $i < $count; ++$i) {
            $name = $this->segmentName($segments[$i]);
            if ($name === null) {
                return null;
            }

            # `withAnyParameters()` places no constraint at all, which is Double's default, so the link
            # simply drops out of the rebuilt chain. Not a mock signal on its own — a `will*`/`expects`
            # elsewhere in the chain still has to confirm it.
            if ($name === 'withAnyParameters') {
                continue;
            }

            # `willReturnSelf()` → `returns(<the double>)`: PHPUnit returns the mock object, Double
            # returns whatever value it is handed, so handing it the chain root reproduces the fluent
            # self-return. Needs the root expression, which only this scope has, so it is not folded
            # into rewriteSegment(); an over-complex root that can't be safely cloned aborts the chain.
            if ($name === 'willReturnSelf') {
                $returnSelf = $segments[$i]->args === [] ? $this->returnSelf($root) : null;
                if ($returnSelf === null) {
                    return null;
                }

                $result = new MethodCall($result, new Identifier($returnSelf[0]), $returnSelf[1]);
                $isMock = true;
                continue;
            }

            if ($name === 'expects') {
                $matcher = $this->analyzeMatcher($segments[$i]->args[0] ?? null);
                $methodSegment = $segments[$i + 1] ?? null;
                if ($matcher === null || $methodSegment === null || $this->segmentName($methodSegment) !== 'method') {
                    return null;
                }

                $result = new MethodCall($result, new Identifier($matcher['verb']), $methodSegment->args);
                if ($matcher['call'] !== null) {
                    $result = new MethodCall($result, new Identifier($matcher['call'][0]), $matcher['call'][1]);
                }
                $isMock = true;
                ++$i;
                continue;
            }

            $rewrite = $this->rewriteSegment($name, $segments[$i], $root);
            if ($rewrite === null) {
                return null;
            }

            $result = new MethodCall($result, new Identifier($rewrite[0]), $rewrite[1]);
            $isMock = $isMock || $rewrite[2];
        }

        return $isMock ? $result : null;
    }

    private function isFactoryRoot(MethodCall $call): bool
    {
        if (PhpunitMockFactory::parse($call) !== null) {
            return true;
        }

        return ($this->isName($call->name, 'strict') || $this->isName($call->name, 'passthru'))
            && $call->var instanceof StaticCall
            && $this->isName($call->var->class, 'JMac\\Testing\\Double')
            && $this->isName($call->var->name, 'for');
    }

    /**
     * Maps a single non-`expects` chain link to `[verb, args, isMockSignal]`, or null when the link
     * has no faithful Double counterpart and the whole chain must be left alone.
     *
     * @return array{0: non-empty-string, 1: list<Arg|VariadicPlaceholder>, 2: bool}|null
     */
    private function rewriteSegment(string $name, MethodCall $segment, Node\Expr $root): ?array
    {
        return match ($name) {
            # A bare stub method (`$stub->method('m')`), not yet a signal on its own — a following
            # `will*` confirms it is a mock chain.
            'method' => ['allows', $segment->args, false],
            'with' => $this->mapWith($segment->args),
            'willReturn', 'willReturnOnConsecutiveCalls' => ['returns', $segment->args, true],
            'willThrowException' => ['throws', $segment->args, true],
            'willReturnCallback' => ['resolves', $segment->args, true],
            'willReturnArgument' => $this->mapReturnArgument($segment->args),
            'willReturnMap' => $this->mapReturnMap($segment->args),
            'will' => $this->mapWill($segment->args[0] ?? null, $root),
            default => null,
        };
    }

    /**
     * Maps a `with()` call, translating each PHPUnit argument constraint to its `Argument::*` matcher (or
     * bare value). A plain value passes through; a constraint with no faithful matcher aborts the whole
     * chain, since leaving the raw `$this->…()` call would break once the test loses its TestCase base.
     *
     * @param list<Arg|VariadicPlaceholder> $args
     * @return array{0: non-empty-string, 1: list<Arg|VariadicPlaceholder>, 2: bool}|null
     */
    private function mapWith(array $args): ?array
    {
        $mapped = [];
        foreach ($args as $arg) {
            if (!$arg instanceof Arg) {
                $mapped[] = $arg;
                continue;
            }

            $constraint = $this->mapConstraintValue($arg->value);
            if ($constraint === null) {
                return null;
            }

            $mapped[] = new Arg($constraint);
        }

        return ['with', $mapped, false];
    }

    /**
     * Maps a single `with()` argument expression to its Double matcher expression: a plain value passes
     * through unchanged; a PHPUnit constraint becomes the matching `Argument::*` matcher, a bare value
     * (for `equalTo`, whose value already matches by equality), or a `satisfies()` predicate for the
     * constraints Double has no dedicated matcher for; one with no faithful form returns null to abort the
     * whole chain. Recursive, so `logicalNot`/`logicalOr` can wrap any mappable inner constraint.
     */
    private function mapConstraintValue(Node\Expr $value): ?Node\Expr
    {
        $name = PhpunitConstraint::name($value);
        if ($name === null) {
            return $value;
        }

        $args = PhpunitConstraint::arguments($value);
        if ($args === null) {
            return null;
        }

        $first = $args[0] ?? null;
        $single = \count($args) === 1;

        # `isType('integer')`, PHPUnit 12's `isInt()`, … → `Argument::type('int')` for the names Double's
        # `type()` checks natively; the rest (`numeric`, `scalar`, `resource`) fall through to the predicate.
        $checkedType = PhpunitConstraint::checkedType($value);
        if (\in_array($checkedType, self::DOUBLE_TYPES, true)) {
            return $this->argument('type', [new Arg(new String_($checkedType))]);
        }

        $dedicated = match ($name) {
            'anything' => $args === [] ? $this->argument('any') : null,
            'equalTo' => $single ? $first : null,
            'identicalTo' => $single ? $this->argument('same', [new Arg($first)]) : null,
            'isInstanceOf' => $single ? $this->argument('type', [new Arg($first)]) : null,
            'callback' => $single ? $this->argument('satisfies', [new Arg($first)]) : null,
            'contains', 'containsEqual' => $single ? $this->argument('contains', [new Arg($first)]) : null,
            'containsIdentical' => $single ? $this->argument('contains', [new Arg($this->argument('same', [new Arg($first)]))]) : null,
            'matchesRegularExpression' => $single ? $this->argument('matches', [new Arg($first)]) : null,
            'isNull' => $args === [] ? new ConstFetch(new Name('null')) : null,
            'isTrue' => $args === [] ? new ConstFetch(new Name('true')) : null,
            'isFalse' => $args === [] ? new ConstFetch(new Name('false')) : null,
            'logicalNot' => $single ? $this->negateConstraint($first) : null,
            'logicalOr' => $this->anyOfConstraints($args),
            default => null,
        };
        if ($dedicated !== null) {
            return $dedicated;
        }

        $constraint = new PhpunitConstraint($this->predicateName);
        $predicate = $constraint->predicate($value);

        return $predicate === null ? null : $this->argument('satisfies', [new Arg($constraint->closure($predicate))]);
    }

    /**
     * `logicalNot($constraint)` → the negated matcher: `Argument::not($value)` for a bare/`equalTo` inner,
     * or `Argument::not()->type()/same()/satisfies()/contains()/matches()/any()` for an inner that maps to
     * one of the matchers `NegatedArgument` mirrors. Anything else (e.g. negating `anything()`) returns
     * null and falls through to the predicate.
     */
    private function negateConstraint(?Node\Expr $inner): ?Node\Expr
    {
        if ($inner === null) {
            return null;
        }

        $mapped = $this->mapConstraintValue($inner);
        if ($mapped === null) {
            return null;
        }

        if (!$this->isArgumentCall($mapped)) {
            return $this->argument('not', [new Arg($mapped)]);
        }

        \assert($mapped instanceof StaticCall);
        $method = $mapped->name instanceof Identifier ? $mapped->name->toString() : null;

        # `not()` only mirrors these verbs, and `not()->any()` needs at least one alternative — negating a
        # bare `anything()` (`Argument::any()` with no args) has no useful meaning.
        if (!\in_array($method, ['type', 'same', 'satisfies', 'contains', 'matches', 'any'], true) || $mapped->args === []) {
            return null;
        }

        return new MethodCall($this->argument('not'), new Identifier($method), $mapped->args);
    }

    /**
     * `logicalOr($a, $b, …)` → `Argument::any($a, $b, …)`, each alternative mapped through
     * {@see mapConstraintValue()} (a value or a nested matcher). Returns null if any alternative is
     * unmappable, leaving the predicate to try.
     *
     * @param list<Node\Expr> $args
     */
    private function anyOfConstraints(array $args): ?StaticCall
    {
        $alternatives = [];
        foreach ($args as $arg) {
            $mapped = $this->mapConstraintValue($arg);
            if ($mapped === null) {
                return null;
            }

            $alternatives[] = new Arg($mapped);
        }

        return $alternatives === [] ? null : $this->argument('any', $alternatives);
    }

    /**
     * True when an expression is one of the `\JMac\Testing\Matching\Argument::*` matcher calls this rule
     * builds — used to tell a mapped matcher apart from a mapped bare value when negating.
     */
    private function isArgumentCall(Node\Expr $value): bool
    {
        return $value instanceof StaticCall
            && $value->class instanceof FullyQualified
            && $value->class->toString() === 'JMac\\Testing\\Matching\\Argument';
    }

    /**
     * `willReturnArgument($n)` → `resolves(fn (...$args) => $args[$n])`, so the Nth call argument is
     * returned the same way PHPUnit echoes it back.
     *
     * @param list<Arg|VariadicPlaceholder> $args
     * @return array{0: non-empty-string, 1: list<Arg>, 2: bool}|null
     */
    private function mapReturnArgument(array $args): ?array
    {
        $index = ($args[0] ?? null) instanceof Arg ? $args[0]->value : null;
        if (!$index instanceof Node\Expr) {
            return null;
        }

        $resolver = new ArrowFunction([
            'params' => [new Param(new Variable('args'), null, null, false, true)],
            'expr' => new ArrayDimFetch(new Variable('args'), $index),
        ]);

        return ['resolves', [new Arg($resolver)], true];
    }

    /**
     * `willReturnMap($map)` → `resolves(<lookup>)`.
     *
     * @param list<Arg|VariadicPlaceholder> $args
     * @return array{0: non-empty-string, 1: list<Arg>, 2: bool}|null
     */
    private function mapReturnMap(array $args): ?array
    {
        $map = \count($args) === 1 && $args[0] instanceof Arg && !$args[0]->unpack ? $args[0]->value : null;
        $resolver = $map === null ? null : ReturnValueMap::resolver($map);

        return $resolver === null ? null : ['resolves', [new Arg($resolver)], true];
    }

    /**
     * A fresh copy of the double's root expression, for reuse as the `returns()` argument of a
     * converted `willReturnSelf()` or as the target of an added `allows()`. Only the two shapes a mock is
     * realistically held in — a local variable (`$mock`) and a `$this->mock` property — are rebuilt;
     * anything else returns null so the conversion is left for manual migration rather than aliasing a
     * node into two positions of the tree.
     */
    private function cloneDoubleRoot(Node\Expr $root): ?Node\Expr
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
     * @param list<Arg> $args
     */
    private function argument(string $method, array $args = []): StaticCall
    {
        return new StaticCall(new FullyQualified('JMac\\Testing\\Matching\\Argument'), new Identifier($method), $args);
    }

    /**
     * Legacy `will($this->returnValue()/throwException()/returnCallback()/onConsecutiveCalls()/
     * returnArgument()/returnSelf()/returnValueMap())` → the matching Double verb — the pre-`willReturn*`
     * spelling of the same return shapes.
     *
     * @return array{0: non-empty-string, 1: list<Arg|VariadicPlaceholder>, 2: bool}|null
     */
    private function mapWill(Arg|VariadicPlaceholder|null $arg, Node\Expr $root): ?array
    {
        if (!$arg instanceof Arg) {
            return null;
        }

        $inner = $arg->value;
        if (!$inner instanceof MethodCall && !$inner instanceof StaticCall) {
            return null;
        }

        return match (true) {
            $this->isName($inner->name, 'returnValue') => ['returns', $inner->args, true],
            $this->isName($inner->name, 'throwException') => ['throws', $inner->args, true],
            $this->isName($inner->name, 'returnCallback') => ['resolves', $inner->args, true],
            $this->isName($inner->name, 'onConsecutiveCalls') => $inner->args === [] ? null : ['returns', $inner->args, true],
            $this->isName($inner->name, 'returnArgument') => $this->mapReturnArgument($inner->args),
            $this->isName($inner->name, 'returnSelf') => $this->returnSelf($root),
            $this->isName($inner->name, 'returnValueMap') => $this->mapReturnMap($inner->args),
            default => null,
        };
    }

    /**
     * `willReturnSelf()` / `will($this->returnSelf())` → `returns(<the double>)`. Needs the chain root
     * cloned into `returns()`; an over-complex root that can't be safely cloned aborts the chain.
     *
     * @return array{0: non-empty-string, 1: list<Arg>, 2: bool}|null
     */
    private function returnSelf(Node\Expr $root): ?array
    {
        $self = $this->cloneDoubleRoot($root);

        return $self === null ? null : ['returns', [new Arg($self)], true];
    }

    /**
     * Turns a PHPUnit invocation matcher (`$this->once()`, `self::exactly(2)`, …) into the verb the
     * expectation should carry plus an optional trailing `times()`/`never()` call. Returns null for a
     * variable or unrecognised matcher, aborting the conversion.
     *
     * @return array{verb: 'expects'|'allows', call: array{0: non-empty-string, 1: list<Arg>}|null}|null
     */
    private function analyzeMatcher(Arg|VariadicPlaceholder|null $arg): ?array
    {
        if (!$arg instanceof Arg) {
            return null;
        }

        $matcher = $arg->value;
        if (!$matcher instanceof MethodCall && !$matcher instanceof StaticCall) {
            return null;
        }

        $argument = $matcher->args[0] ?? null;
        $value = $argument instanceof Arg ? $argument->value : null;

        return match (true) {
            $this->isName($matcher->name, 'any') => ['verb' => 'allows', 'call' => null],
            $this->isName($matcher->name, 'once') => ['verb' => 'expects', 'call' => ['times', [new Arg(new Int_(1))]]],
            $this->isName($matcher->name, 'never') => ['verb' => 'expects', 'call' => ['never', []]],
            $this->isName($matcher->name, 'exactly') && $value !== null => ['verb' => 'expects', 'call' => ['times', [new Arg($value)]]],
            $this->isName($matcher->name, 'atLeastOnce') => ['verb' => 'expects', 'call' => ['times', [new Arg(new Int_(1), name: new Identifier('minimum'))]]],
            $this->isName($matcher->name, 'atLeast') && $value !== null => ['verb' => 'expects', 'call' => ['times', [new Arg($value, name: new Identifier('minimum'))]]],
            $this->isName($matcher->name, 'atMost') && $value !== null => ['verb' => 'expects', 'call' => ['times', [new Arg($value, name: new Identifier('maximum'))]]],
            default => null,
        };
    }

    private function segmentName(MethodCall $segment): ?string
    {
        return $segment->name instanceof Identifier ? $segment->name->toString() : null;
    }

    /**
     * @param list<Arg> $args
     */
    private function doubleCall(array $args): StaticCall
    {
        return new StaticCall(new FullyQualified('JMac\\Testing\\Double'), new Identifier('for'), $args);
    }
}
