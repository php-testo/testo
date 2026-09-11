<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
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
 * - `$this->createMock(X)` / `$this->createStub(X)` → `Double::for(X)`.
 * - a configuration chain is rebuilt from its outermost call: PHPUnit's invocation matcher moves
 *   off `expects()` and onto the verb — `$this->any()` picks `allows()` (optional), every other
 *   matcher keeps `expects()` (required) and folds into a trailing `times()`/`never()`; the method
 *   name moves from `->method('m')` onto `expects('m')`/`allows('m')`; `->with()` is kept; and the
 *   return verbs map `willReturn`/`willReturnOnConsecutiveCalls` → `returns`, `willThrowException`
 *   → `throws`, `willReturnCallback` → `resolves`, plus the legacy `will($this->returnValue()/
 *   throwException()/returnCallback())` wrappers onto the same three.
 *
 * Matcher map: `once` → `times(1)`, `exactly($n)` → `times($n)`, `never` → `never()`,
 * `atLeastOnce` → `times(minimum: 1)`, `atLeast($n)` → `times(minimum: $n)`,
 * `atMost($n)` → `times(maximum: $n)`, `any` → `allows()` (no count).
 *
 * Conservative by design: a chain is rewritten only when it carries a PHPUnit mock signal — an
 * `expects()` with a recognised matcher, or one of the `will*` return verbs — so an unrelated
 * fluent chain is left alone. Any unrecognised link (`willReturnMap`, `willReturnSelf`,
 * `willReturnArgument`, a variable matcher, `getMockBuilder()`, `prophesize()`) aborts the whole
 * chain rather than converting it in part; those stay for manual migration (see
 * {@see MockToTestoRector} and TODO.md). Argument constraints inside `with()` (`$this->equalTo()`,
 * `$this->anything()`, …) are passed through untouched — a literal expected value converts cleanly,
 * a constraint object does not and is left for the author to map onto an `Argument::*` matcher.
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
     * `$this->createMock(X)` / `$this->createStub(X)` → `Double::for(X)`.
     */
    private function matchMockFactory(MethodCall $node): ?StaticCall
    {
        if (!$this->isName($node->var, 'this')) {
            return null;
        }

        if (!$this->isName($node->name, 'createMock') && !$this->isName($node->name, 'createStub')) {
            return null;
        }

        return $this->doubleFor($node->args);
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
            'with' => ['with', $segment->args, false],
            'willReturn', 'willReturnOnConsecutiveCalls' => ['returns', $segment->args, true],
            'willThrowException' => ['throws', $segment->args, true],
            'willReturnCallback' => ['resolves', $segment->args, true],
            'will' => $this->mapWill($segment->args[0] ?? null),
            default => null,
        };
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
