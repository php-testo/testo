<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
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
 * - `$this->createMock(X)` / `$this->createStub(X)` → `Double::for(X)`, and
 *   `createMockForIntersectionOfInterfaces([A, B])` → `Double::for(A, B)`.
 * - a configuration chain is rebuilt from its outermost call: PHPUnit's invocation matcher moves
 *   off `expects()` and onto the verb — `$this->any()` picks `allows()` (optional), every other
 *   matcher keeps `expects()` (required) and folds into a trailing `times()`/`never()`; the method
 *   name moves from `->method('m')` onto `expects('m')`/`allows('m')`; and the return verbs map
 *   `willReturn`/`willReturnOnConsecutiveCalls` → `returns`, `willThrowException` → `throws`,
 *   `willReturnCallback` → `resolves`, `willReturnArgument($n)` → `resolves(fn (...$a) => $a[$n])`,
 *   plus the legacy `will($this->returnValue()/throwException()/returnCallback())` wrappers.
 * - `->with()` argument constraints become `Argument::*` matchers — `anything()` → `any()`,
 *   `identicalTo()` → `same()`, `isInstanceOf()`/`isType()` → `type()`, `callback()` → `satisfies()`,
 *   `contains()` → `contains()`, `matchesRegularExpression()` → `matches()`; `equalTo($x)` unwraps to
 *   the bare `$x` (Double matches by equality by default); a plain value passes through.
 *
 * Matcher map: `once` → `times(1)`, `exactly($n)` → `times($n)`, `never` → `never()`,
 * `atLeastOnce` → `times(minimum: 1)`, `atLeast($n)` → `times(minimum: $n)`,
 * `atMost($n)` → `times(maximum: $n)`, `any` → `allows()` (no count).
 *
 * Conservative by design: a chain is rewritten only when it carries a PHPUnit mock signal — an
 * `expects()` with a recognised matcher, or one of the `will*` return verbs — so an unrelated
 * fluent chain is left alone. Any link with no faithful counterpart aborts the whole chain rather
 * than converting it in part: `willReturnMap`/`willReturnSelf`, a variable matcher, `getMockBuilder()`,
 * `prophesize()`, or a `with()` constraint that has no `Argument::*` form (`stringContains`,
 * `greaterThan`, `logicalOr`, …) — leaving a raw `$this->…()` constraint would break once the test
 * loses its TestCase base. Those stay for manual migration (see {@see MockToTestoRector} and TODO.md).
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
        if (!$this->isName($node->var, 'this')) {
            return null;
        }

        if ($this->isName($node->name, 'createMock') || $this->isName($node->name, 'createStub')) {
            return $this->doubleFor($node->args);
        }

        if ($this->isName($node->name, 'createMockForIntersectionOfInterfaces')) {
            return $this->intersectionDouble($node->args);
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

        $result = $segments[0]->var;
        $isMock = false;
        $count = \count($segments);

        for ($i = 0; $i < $count; ++$i) {
            $name = $this->segmentName($segments[$i]);
            if ($name === null) {
                return null;
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

            $rewrite = $this->rewriteSegment($name, $segments[$i]);
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
    private function rewriteSegment(string $name, MethodCall $segment): ?array
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
            'will' => $this->mapWill($segment->args[0] ?? null),
            default => null,
        };
    }

    /**
     * Maps a `with()` call, translating PHPUnit argument constraints to `Argument::*` matchers. A plain
     * value passes through; a `$this->`/`self::` call is treated as a constraint and mapped, or — when
     * its name has no faithful matcher (`stringContains`, `greaterThan`, `logicalOr`, …) — aborts the
     * whole chain, since leaving the raw constraint call would break once the test loses its TestCase base.
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

            $constraint = $this->mapConstraint($arg);
            if ($constraint === null) {
                return null;
            }

            $mapped[] = $constraint;
        }

        return ['with', $mapped, false];
    }

    /**
     * A single `with()` argument: a PHPUnit constraint (`$this->equalTo()`, `$this->anything()`, …)
     * mapped to its `Argument::*` form (or unwrapped for `equalTo`, whose value already matches by
     * equality), a plain value returned unchanged, or null to abort when a `$this->`/`self::` call has
     * no faithful matcher.
     */
    private function mapConstraint(Arg $arg): ?Arg
    {
        $value = $arg->value;
        $isConstraintCall = ($value instanceof MethodCall && $this->isName($value->var, 'this'))
            || ($value instanceof StaticCall && ($this->isName($value->class, 'self') || $this->isName($value->class, 'static')));
        if (!$isConstraintCall) {
            return $arg;
        }

        \assert($value instanceof MethodCall || $value instanceof StaticCall);
        $name = $value->name instanceof Identifier ? $value->name->toString() : null;
        $inner = ($value->args[0] ?? null) instanceof Arg ? $value->args[0]->value : null;

        return match ($name) {
            'anything' => new Arg($this->argument('any')),
            'equalTo' => $inner !== null ? new Arg($inner) : null,
            'identicalTo' => $inner !== null ? new Arg($this->argument('same', [new Arg($inner)])) : null,
            'isInstanceOf', 'isType' => $inner !== null ? new Arg($this->argument('type', [new Arg($inner)])) : null,
            'callback' => $inner !== null ? new Arg($this->argument('satisfies', [new Arg($inner)])) : null,
            'contains' => $inner !== null ? new Arg($this->argument('contains', [new Arg($inner)])) : null,
            'matchesRegularExpression' => $inner !== null ? new Arg($this->argument('matches', [new Arg($inner)])) : null,
            default => null,
        };
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
     * @param list<Arg> $args
     */
    private function argument(string $method, array $args = []): StaticCall
    {
        return new StaticCall(new FullyQualified('JMac\\Testing\\Matching\\Argument'), new Identifier($method), $args);
    }

    /**
     * Legacy `will($this->returnValue()/throwException()/returnCallback())` → the matching Double verb.
     *
     * @return array{0: non-empty-string, 1: list<Arg|\PhpParser\Node\VariadicPlaceholder>, 2: bool}|null
     */
    private function mapWill(Arg|\PhpParser\Node\VariadicPlaceholder|null $arg): ?array
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
            default => null,
        };
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
