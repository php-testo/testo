<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

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
 * Two transforms cooperate over Rector's fix-point passes:
 *
 * - `$this->createMock(X)` / `$this->createStub(X)` → `Double::for(X)`,
 *   `create{Mock,Stub}ForIntersectionOfInterfaces([A, B])` → `Double::for(A, B)`, and the
 *   constructor-disabling builder chain `getMockBuilder(X)->disableOriginalConstructor()->getMock()`
 *   → `Double::for(X)`.
 * - a configuration chain is rebuilt from its outermost call: PHPUnit's invocation matcher moves
 *   off `expects()` and onto the verb — `$this->any()` picks `allows()` (optional), every other
 *   matcher keeps `expects()` (required) and folds into a trailing `times()`/`never()`; the method
 *   name moves from `->method('m')` onto `expects('m')`/`allows('m')`; `withAnyParameters()` drops
 *   away (Double's default); and the return verbs map
 *   `willReturn`/`willReturnOnConsecutiveCalls` → `returns`, `willThrowException` → `throws`,
 *   `willReturnCallback` → `resolves`, `willReturnArgument($n)` → `resolves(fn (...$a) => $a[$n])`,
 *   `willReturnSelf()` → `returns(<the double>)`, plus the legacy `will($this->returnValue()/
 *   throwException()/returnCallback()/onConsecutiveCalls()/returnArgument()/returnSelf())` wrappers.
 * - `->with()` argument constraints become `Argument::*` matchers: `anything()` → `any()`,
 *   `identicalTo()` → `same()`, `isInstanceOf()`/`isType()` → `type()`, `callback()` → `satisfies()`,
 *   `contains()` → `contains()`, `matchesRegularExpression()` → `matches()`; `equalTo($x)` unwraps to
 *   the bare `$x` and `isNull()`/`isTrue()`/`isFalse()` to `null`/`true`/`false` (Double matches by
 *   equality by default); the comparison and string constraints Double has no dedicated matcher for
 *   become a predicate — `greaterThan`/`lessThan`/`greaterThanOrEqual`/`lessThanOrEqual`,
 *   `isEmpty`, `stringContains`, `stringStartsWith`/`stringEndsWith`, `arrayHasKey` →
 *   `satisfies(fn ($value) => …)`; and the composites fold in recursively — `logicalNot()` →
 *   `Argument::not(...)` / `Argument::not()->…()`, `logicalOr()` → `Argument::any(...)`. A plain value
 *   passes through.
 *
 * Matcher map: `once` → `times(1)`, `exactly($n)` → `times($n)`, `never` → `never()`,
 * `atLeastOnce` → `times(minimum: 1)`, `atLeast($n)` → `times(minimum: $n)`,
 * `atMost($n)` → `times(maximum: $n)`, `any` → `allows()` (no count).
 *
 * Conservative by design: a chain is rewritten only when it carries a PHPUnit mock signal — an
 * `expects()` with a recognised matcher, or one of the `will*` return verbs — so an unrelated
 * fluent chain is left alone. Any link with no faithful counterpart aborts the whole chain rather
 * than converting it in part: `willReturnMap`, a variable matcher, `prophesize()`, a builder step
 * beyond `disableOriginalConstructor()` (or the bare constructor-calling `getMockBuilder(X)->getMock()`),
 * or a `with()` constraint with no faithful form (`logicalAnd` — no per-argument AND matcher;
 * `equalToWithDelta`/`equalToCanonicalizing` — loose comparison; a case-insensitive `stringContains`)
 * — leaving a raw `$this->…()` constraint would break once the test loses its TestCase base. Those
 * stay for manual migration (see {@see MockToTestoRector} and TODO.md).
 */
#[TestRectorFixtures('CreateMockToDoubleRector')]
final class CreateMockToDoubleRector extends AbstractRector
{
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
     * never interfere: an unconvertible outer link (e.g. `willReturnSelf()`) leaves the whole statement
     * alone instead of the inner `expects()->method()` being rewritten on its own by a call-level visit.
     *
     * @param Expression|MethodCall $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof MethodCall) {
            return $this->matchMockFactory($node);
        }

        if (!$node->expr instanceof MethodCall) {
            return null;
        }

        $rebuilt = $this->rebuildMockChain($node->expr);
        if ($rebuilt === null) {
            return null;
        }

        $node->expr = $rebuilt;

        return $node;
    }

    /**
     * `$this->createMock(X)` / `$this->createStub(X)` → `Double::for(X)`, and
     * `$this->createMockForIntersectionOfInterfaces([A, B])` → `Double::for(A, B)` (an array literal
     * only — a computed target list has nothing to unpack and is left alone).
     */
    private function matchMockFactory(MethodCall $node): ?StaticCall
    {
        # A builder chain (`$this->getMockBuilder(X)->…->getMock()`) roots on the builder, not `$this`,
        # so it is matched before the `$this->…` factory forms below.
        if ($this->isName($node->name, 'getMock')) {
            return $this->builderDouble($node);
        }

        if (!$this->isName($node->var, 'this')) {
            return null;
        }

        if ($this->isName($node->name, 'createMock') || $this->isName($node->name, 'createStub')) {
            return $this->doubleFor($node->args);
        }

        if (
            $this->isName($node->name, 'createMockForIntersectionOfInterfaces')
            || $this->isName($node->name, 'createStubForIntersectionOfInterfaces')
        ) {
            return $this->intersectionDouble($node->args);
        }

        return null;
    }

    /**
     * `$this->getMockBuilder(X)->disableOriginalConstructor()->getMock()` → `Double::for(X)`.
     *
     * Only the constructor-disabling builder chain converts. `Double::for()` never runs the target's
     * real constructor (it instantiates without it), so `disableOriginalConstructor()` merely restates
     * the Double default and drops away — while a *bare* `getMockBuilder(X)->getMock()` does call the
     * real constructor, so it is deliberately left alone rather than silently changed. Any other builder
     * step (`onlyMethods`, `setConstructorArgs`, `getMockForAbstractClass`, …) changes what is doubled
     * and has no single-call Double form, so the whole chain is left for manual migration.
     */
    private function builderDouble(MethodCall $getMock): ?StaticCall
    {
        if ($getMock->args !== []) {
            return null;
        }

        $sawDisableConstructor = false;
        $cursor = $getMock->var;
        while ($cursor instanceof MethodCall) {
            $name = $this->segmentName($cursor);

            if ($name === 'disableOriginalConstructor' && $cursor->args === []) {
                $sawDisableConstructor = true;
                $cursor = $cursor->var;
                continue;
            }

            if ($name === 'getMockBuilder' && $this->isName($cursor->var, 'this')) {
                return $sawDisableConstructor ? $this->doubleFor($cursor->args) : null;
            }

            return null;
        }

        return null;
    }

    /**
     * @param list<Arg|\PhpParser\Node\VariadicPlaceholder> $args
     */
    private function intersectionDouble(array $args): ?StaticCall
    {
        $first = $args[0] ?? null;
        if (!$first instanceof Arg || !$first->value instanceof Array_) {
            return null;
        }

        $targets = [];
        foreach ($first->value->items as $item) {
            if ($item === null) {
                return null;
            }

            $targets[] = new Arg($item->value);
        }

        return $targets === [] ? null : $this->doubleFor($targets);
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
        $segments = [];
        $cursor = $node;
        while ($cursor instanceof MethodCall) {
            $segments[] = $cursor;
            $cursor = $cursor->var;
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
                $returnSelf = $this->returnSelf($root);
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

    /**
     * Maps a single non-`expects` chain link to `[verb, args, isMockSignal]`, or null when the link
     * has no faithful Double counterpart and the whole chain must be left alone.
     *
     * @return array{0: non-empty-string, 1: list<Arg|\PhpParser\Node\VariadicPlaceholder>, 2: bool}|null
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
            'will' => $this->mapWill($segment->args[0] ?? null, $root),
            default => null,
        };
    }

    /**
     * Maps a `with()` call, translating each PHPUnit argument constraint to its `Argument::*` matcher (or
     * bare value). A plain value passes through; a constraint with no faithful matcher aborts the whole
     * chain, since leaving the raw `$this->…()` call would break once the test loses its TestCase base.
     *
     * @param list<Arg|\PhpParser\Node\VariadicPlaceholder> $args
     * @return array{0: non-empty-string, 1: list<Arg|\PhpParser\Node\VariadicPlaceholder>, 2: bool}|null
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
     * through unchanged; a PHPUnit constraint (`$this->equalTo()`, `$this->greaterThan()`,
     * `$this->logicalNot()`, …) becomes the matching `Argument::*` matcher, a bare value (for `equalTo`,
     * whose value already matches by equality), or a `satisfies()` predicate for the comparison/string
     * constraints Double has no dedicated matcher for; an unmappable constraint returns null to abort the
     * whole chain. Recursive, so `logicalNot`/`logicalOr` can wrap any mappable inner constraint.
     */
    private function mapConstraintValue(Node\Expr $value): ?Node\Expr
    {
        if (!$this->isConstraintCall($value)) {
            return $value;
        }

        \assert($value instanceof MethodCall || $value instanceof StaticCall);
        $name = $value->name instanceof Identifier ? $value->name->toString() : null;
        $args = $value->args;
        $first = ($args[0] ?? null) instanceof Arg ? $args[0]->value : null;

        return match ($name) {
            'anything' => $this->argument('any'),
            'equalTo' => $first,
            'identicalTo' => $first !== null ? $this->argument('same', [new Arg($first)]) : null,
            'isInstanceOf', 'isType' => $first !== null ? $this->argument('type', [new Arg($first)]) : null,
            'callback' => $first !== null ? $this->argument('satisfies', [new Arg($first)]) : null,
            'contains' => $first !== null ? $this->argument('contains', [new Arg($first)]) : null,
            'matchesRegularExpression' => $first !== null ? $this->argument('matches', [new Arg($first)]) : null,
            'isNull' => new ConstFetch(new Name('null')),
            'isTrue' => new ConstFetch(new Name('true')),
            'isFalse' => new ConstFetch(new Name('false')),
            'greaterThan' => $first !== null ? $this->satisfies(new BinaryOp\Greater($this->predicateVar(), $first)) : null,
            'lessThan' => $first !== null ? $this->satisfies(new BinaryOp\Smaller($this->predicateVar(), $first)) : null,
            'greaterThanOrEqual' => $first !== null ? $this->satisfies(new BinaryOp\GreaterOrEqual($this->predicateVar(), $first)) : null,
            'lessThanOrEqual' => $first !== null ? $this->satisfies(new BinaryOp\SmallerOrEqual($this->predicateVar(), $first)) : null,
            'isEmpty' => $this->satisfies(new Empty_($this->predicateVar())),
            'stringContains' => $this->stringPredicate('str_contains', $args, $first),
            'stringStartsWith' => $first !== null ? $this->satisfies($this->func('str_starts_with', [$this->predicateVar(), $first])) : null,
            'stringEndsWith' => $first !== null ? $this->satisfies($this->func('str_ends_with', [$this->predicateVar(), $first])) : null,
            'arrayHasKey' => $first !== null ? $this->satisfies($this->func('array_key_exists', [$first, $this->predicateVar()])) : null,
            'logicalNot' => $this->negateConstraint($first),
            'logicalOr' => $this->anyOfConstraints($args),
            default => null,
        };
    }

    /**
     * True when an expression is a PHPUnit constraint factory call — `$this->equalTo(...)` or the
     * `self::`/`static::` static forms — as opposed to a plain value passed straight to `with()`.
     */
    private function isConstraintCall(Node\Expr $value): bool
    {
        return ($value instanceof MethodCall && $this->isName($value->var, 'this'))
            || ($value instanceof StaticCall && ($this->isName($value->class, 'self') || $this->isName($value->class, 'static')));
    }

    /**
     * `logicalNot($constraint)` → the negated matcher: `Argument::not($value)` for a bare/`equalTo` inner,
     * or `Argument::not()->type()/same()/satisfies()/contains()/matches()/any()` for an inner that maps to
     * one of the matchers `NegatedArgument` mirrors. Anything else (e.g. negating `anything()`) aborts.
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
     * {@see mapConstraintValue()} (a value or a nested matcher). Aborts if any alternative is unmappable.
     *
     * @param list<Arg|\PhpParser\Node\VariadicPlaceholder> $args
     */
    private function anyOfConstraints(array $args): ?StaticCall
    {
        $alternatives = [];
        foreach ($args as $arg) {
            if (!$arg instanceof Arg) {
                return null;
            }

            $mapped = $this->mapConstraintValue($arg->value);
            if ($mapped === null) {
                return null;
            }

            $alternatives[] = new Arg($mapped);
        }

        return $alternatives === [] ? null : $this->argument('any', $alternatives);
    }

    /**
     * `stringContains($needle)` → `Argument::satisfies(fn ($value) => str_contains($value, $needle))`.
     * PHPUnit's optional case-insensitivity flag has no `str_contains` equivalent, so a call carrying a
     * second argument aborts rather than silently dropping it.
     *
     * @param list<Arg|\PhpParser\Node\VariadicPlaceholder> $args
     */
    private function stringPredicate(string $function, array $args, ?Node\Expr $needle): ?StaticCall
    {
        if ($needle === null || \count($args) !== 1) {
            return null;
        }

        return $this->satisfies($this->func($function, [$this->predicateVar(), $needle]));
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
     * `Argument::satisfies(fn ($value) => <predicate>)` — the shared shape for every comparison/string
     * constraint Double expresses through a predicate rather than a dedicated matcher.
     */
    private function satisfies(Node\Expr $predicate): StaticCall
    {
        $closure = new ArrowFunction([
            'params' => [new Param($this->predicateVar())],
            'expr' => $predicate,
        ]);

        return $this->argument('satisfies', [new Arg($closure)]);
    }

    private function predicateVar(): Variable
    {
        return new Variable('value');
    }

    /**
     * @param list<Node\Expr> $args
     */
    private function func(string $name, array $args): FuncCall
    {
        return new FuncCall(new Name($name), \array_map(static fn(Node\Expr $arg): Arg => new Arg($arg), $args));
    }

    /**
     * `willReturnArgument($n)` → `resolves(fn (...$args) => $args[$n])`, so the Nth call argument is
     * returned the same way PHPUnit echoes it back.
     *
     * @param list<Arg|\PhpParser\Node\VariadicPlaceholder> $args
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
     * A fresh copy of the double's root expression, for reuse as the `returns()` argument of a
     * converted `willReturnSelf()`. Only the two shapes a mock is realistically held in — a local
     * variable (`$mock`) and a `$this->mock` property — are rebuilt; anything else returns null so the
     * chain is left for manual migration rather than aliasing a node into two positions of the tree.
     */
    private function cloneDoubleRoot(Node\Expr $root): ?Node\Expr
    {
        if ($root instanceof Variable && \is_string($root->name)) {
            return new Variable($root->name);
        }

        if (
            $root instanceof Node\Expr\PropertyFetch
            && $root->var instanceof Variable
            && \is_string($root->var->name)
            && $root->name instanceof Identifier
        ) {
            return new Node\Expr\PropertyFetch(new Variable($root->var->name), new Identifier($root->name->toString()));
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
     * returnArgument()/returnSelf())` → the matching Double verb — the pre-`willReturn*` spelling of the
     * same return shapes.
     *
     * @return array{0: non-empty-string, 1: list<Arg|\PhpParser\Node\VariadicPlaceholder>, 2: bool}|null
     */
    private function mapWill(Arg|\PhpParser\Node\VariadicPlaceholder|null $arg, Node\Expr $root): ?array
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
    private function analyzeMatcher(Arg|\PhpParser\Node\VariadicPlaceholder|null $arg): ?array
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
     * @param list<Arg|\PhpParser\Node\VariadicPlaceholder> $args
     */
    private function doubleFor(array $args): StaticCall
    {
        return new StaticCall(new FullyQualified('JMac\\Testing\\Double'), new Identifier('for'), $args);
    }
}
