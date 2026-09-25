<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToMockery;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
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
 * Converts a PHPUnit mock/stub — creation and its configuration chain — into the equivalent Mockery
 * form (verified after every test by `testo/bridge-mockery`):
 *
 *     $dep = $this->createMock(Dependency::class);
 *     $dep->expects($this->once())->method('run')->with('x')->willReturn('y');
 *     // becomes
 *     $dep = \Mockery::mock(Dependency::class)->shouldIgnoreMissing();
 *     $dep->shouldReceive('run')->once()->with('x')->andReturn('y');
 *
 * Creation ({@see PhpunitMockFactory} reads the PHPUnit side): a PHPUnit double answers an unconfigured
 * call with a type-appropriate default, and so does an ignore-missing Mockery mock, while a plain one
 * throws — so `createMock(X)`/`createStub(X)`, the intersection factories and the constructor-disabling
 * builder become `\Mockery::mock(...)->shouldIgnoreMissing()`, and `disableAutoReturnValueGeneration()`
 * keeps the plain, throwing mock. `createConfiguredMock(X, $map)` → `\Mockery::mock(X, $map)` (Mockery's
 * quick definitions). A partial double — `createPartialMock(X, ['a'])`, `onlyMethods(['a'])` — becomes
 * Mockery's traditional partial `\Mockery::mock('X[a]')`, with the constructor arguments as the second
 * argument when the builder runs the constructor; an empty method list is `->makePartial()`.
 *
 * Configuration chain: `expects($matcher)->method('m')` and a bare `method('m')` → `shouldReceive('m')`,
 * with the matcher moving onto a count — `once()`, `exactly($n)` → `times($n)`, `never()`,
 * `atLeastOnce()` → `atLeast()->once()`, `atLeast($n)`/`atMost($n)` → `atLeast()/atMost()->times($n)`,
 * `any()` → no count (Mockery's default). `withAnyParameters()` drops away; the returns map
 * `willReturn`/`willReturnOnConsecutiveCalls` → `andReturn`, `willThrowException` → `andThrow`,
 * `willReturnCallback` → `andReturnUsing`, `willReturnArgument` → `andReturnArg`, `willReturnSelf` →
 * `andReturnSelf`, `willReturnMap` → `andReturnUsing(<the map lookup>)` ({@see ReturnValueMap}), plus the
 * legacy `will($this->returnValue()/…/returnValueMap())` wrappers.
 *
 * `with()` constraints: a plain value and `equalTo($x)` stay a plain `$x` (both compare loosely);
 * `anything` → `Mockery::any()`, `identicalTo` → `isSame`, `isInstanceOf`/`isType` → `type`,
 * `callback` → `on`, `matchesRegularExpression` → `pattern`, `arrayHasKey` → `hasKey`,
 * `contains`/`containsEqual` → `hasValue`, `isNull`/`isTrue`/`isFalse` → `isSame(null/true/false)` (a
 * plain literal would compare loosely). Every other constraint becomes `Mockery::on(fn ($value) => …)`
 * over its own PHP expression ({@see PhpunitConstraint}) — comparisons, delta/case/canonicalizing
 * equality, string, count, JSON, file and type checks, and `logicalNot`/`Or`/`And`/`Xor` over any of them.
 *
 * All-or-nothing per chain: a variable matcher, `prophesize()`, `getMockForAbstractClass()`, a builder
 * step with no Mockery form, and `stringContains()` with a computed case flag leave the statement
 * untouched for manual migration (see TODO.md).
 */
#[TestRectorFixtures('CreateMockToMockeryRector')]
#[Covers(self::class)]
#[Covers(PhpunitConstraint::class)]
#[Covers(PhpunitMockFactory::class)]
#[Covers(PredicateVariable::class)]
#[Covers(ReturnValueMap::class)]
final class CreateMockToMockeryRector extends AbstractRector
{
    /**
     * PHP types whose Mockery `type()` check (`is_<type>()`) is PHPUnit's check too. A resource is not:
     * PHPUnit also accepts a closed one, which `is_resource()` rejects.
     */
    private const MOCKERY_TYPES = ['int', 'float', 'bool', 'string', 'array', 'object', 'callable', 'iterable', 'null', 'numeric', 'scalar'];

    /**
     * The variable the predicates of the statement being rebuilt are written over.
     */
    private string $predicateName = 'value';

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert PHPUnit `createMock()`/`createStub()` and their `expects()/method()/will*()` configuration chains into Mockery calls',
            [
                new CodeSample(
                    <<<'PHP'
                        $dep = $this->createMock(Dependency::class);
                        $dep->expects($this->once())->method('run')->with('x')->willReturn('y');
                        PHP,
                    <<<'PHP'
                        $dep = \Mockery::mock(Dependency::class)->shouldIgnoreMissing();
                        $dep->shouldReceive('run')->once()->with('x')->andReturn('y');
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
     * The chain rebuild runs at statement level and the factory rewrite at call level, so an
     * unconvertible outer link leaves the whole statement alone instead of its inner links being
     * rewritten on their own.
     *
     * @param Expression|MethodCall $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof MethodCall) {
            $factory = PhpunitMockFactory::parse($node);

            return $factory === null ? null : $this->mockeryDouble($factory);
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

    private function mockeryDouble(PhpunitMockFactory $factory): ?Node\Expr
    {
        if ($factory->configuration !== null) {
            return $this->ignoreMissing($this->mock([$factory->targets[0], new Arg($factory->configuration)]));
        }

        if ($factory->partialMethods === null) {
            $mock = $this->mock($factory->targets);

            return $factory->autoReturn ? $this->ignoreMissing($mock) : $mock;
        }

        $constructorArgs = $factory->constructorArgs === null ? [] : [new Arg($factory->constructorArgs)];

        # No doubled method at all: every call runs for real, which is Mockery's runtime partial.
        if ($factory->partialMethods === []) {
            return new MethodCall(
                $this->mock([new Arg($factory->target()), ...$constructorArgs]),
                new Identifier('makePartial'),
            );
        }

        $spec = $this->partialSpec($factory->target(), $factory->partialMethods);
        if ($spec === null) {
            return null;
        }

        $mock = $this->mock([new Arg($spec), ...$constructorArgs]);

        return $factory->autoReturn ? $this->ignoreMissing($mock) : $mock;
    }

    /**
     * Mockery's traditional-partial target, `'App\Dependency[run,stop]'`: the listed methods are doubled,
     * the rest run for real. Needs a literal class and literal method names.
     *
     * @param list<Node\Expr> $methods
     */
    private function partialSpec(Node\Expr $target, array $methods): ?String_
    {
        $class = match (true) {
            $target instanceof ClassConstFetch && $this->isName($target->name, 'class') && $target->class instanceof Name => $this->getName($target->class),
            $target instanceof String_ => \ltrim($target->value, '\\'),
            default => null,
        };
        if ($class === null) {
            return null;
        }

        $names = [];
        foreach ($methods as $method) {
            if (!$method instanceof String_) {
                return null;
            }
            $names[] = $method->value;
        }

        return new String_($class . '[' . \implode(',', $names) . ']');
    }

    /**
     * Rebuilds a configuration chain into its Mockery form, or returns null when the chain carries no
     * PHPUnit mock signal or hits a link with no faithful counterpart. Idempotent: the rebuilt chain opens
     * with `shouldReceive()` and uses only Mockery verbs, none of which re-trigger a rewrite.
     */
    private function rebuildMockChain(MethodCall $node): ?MethodCall
    {
        # The chain root may itself be a call: a PHPUnit factory (`$this->createMock(X)->method(…)`) or the
        # Mockery factory it has already become. Either one ends the chain rather than joining it.
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

        $result = $segments[0]->var;
        $isMock = false;
        $count = \count($segments);

        for ($i = 0; $i < $count; ++$i) {
            $name = $this->segmentName($segments[$i]);
            if ($name === null) {
                return null;
            }

            if ($name === 'withAnyParameters') {
                continue;
            }

            if ($name === 'expects') {
                $countCalls = $this->analyzeMatcher($segments[$i]->args[0] ?? null);
                $methodSegment = $segments[$i + 1] ?? null;
                if ($countCalls === null || $methodSegment === null || $this->segmentName($methodSegment) !== 'method') {
                    return null;
                }

                $result = new MethodCall($result, new Identifier('shouldReceive'), $methodSegment->args);
                foreach ($countCalls as [$method, $args]) {
                    $result = new MethodCall($result, new Identifier($method), $args);
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

    private function isFactoryRoot(MethodCall $call): bool
    {
        if (PhpunitMockFactory::parse($call) !== null) {
            return true;
        }

        return ($this->isName($call->name, 'shouldIgnoreMissing') || $this->isName($call->name, 'makePartial'))
            && $call->var instanceof StaticCall
            && $this->isName($call->var->class, 'Mockery')
            && $this->isName($call->var->name, 'mock');
    }

    /**
     * Maps a non-`expects` chain link to `[method, args, isMockSignal]`, or null when it has no faithful
     * Mockery counterpart.
     *
     * @return array{0: non-empty-string, 1: list<Arg|VariadicPlaceholder>, 2: bool}|null
     */
    private function rewriteSegment(string $name, MethodCall $segment): ?array
    {
        return match ($name) {
            # A bare stub method (`$stub->method('m')`) is not a signal on its own — a following `will*`
            # confirms the chain.
            'method' => ['shouldReceive', $segment->args, false],
            'with' => $this->mapWith($segment->args),
            'willReturn', 'willReturnOnConsecutiveCalls' => ['andReturn', $segment->args, true],
            'willThrowException' => ['andThrow', $segment->args, true],
            'willReturnCallback' => ['andReturnUsing', $segment->args, true],
            'willReturnArgument' => ['andReturnArg', $segment->args, true],
            'willReturnSelf' => $segment->args === [] ? ['andReturnSelf', [], true] : null,
            'willReturnMap' => $this->mapReturnMap($segment->args),
            'will' => $this->mapWill($segment->args[0] ?? null),
            default => null,
        };
    }

    /**
     * `willReturnMap($map)` → `andReturnUsing(<lookup>)`.
     *
     * @param list<Arg|VariadicPlaceholder> $args
     * @return array{0: non-empty-string, 1: list<Arg>, 2: bool}|null
     */
    private function mapReturnMap(array $args): ?array
    {
        $map = \count($args) === 1 && $args[0] instanceof Arg && !$args[0]->unpack ? $args[0]->value : null;
        $resolver = $map === null ? null : ReturnValueMap::resolver($map);

        return $resolver === null ? null : ['andReturnUsing', [new Arg($resolver)], true];
    }

    /**
     * Legacy `will($this->returnValue()/throwException()/returnCallback()/onConsecutiveCalls()/
     * returnArgument()/returnSelf()/returnValueMap())` → the matching Mockery return verb.
     *
     * @return array{0: non-empty-string, 1: list<Arg|VariadicPlaceholder>, 2: bool}|null
     */
    private function mapWill(Arg|VariadicPlaceholder|null $arg): ?array
    {
        if (!$arg instanceof Arg) {
            return null;
        }

        $inner = $arg->value;
        if (!$inner instanceof MethodCall && !$inner instanceof StaticCall) {
            return null;
        }

        return match (true) {
            $this->isName($inner->name, 'returnValue') => ['andReturn', $inner->args, true],
            $this->isName($inner->name, 'throwException') => ['andThrow', $inner->args, true],
            $this->isName($inner->name, 'returnCallback') => ['andReturnUsing', $inner->args, true],
            $this->isName($inner->name, 'onConsecutiveCalls') => $inner->args === [] ? null : ['andReturn', $inner->args, true],
            $this->isName($inner->name, 'returnArgument') => ['andReturnArg', $inner->args, true],
            $this->isName($inner->name, 'returnSelf') => $inner->args === [] ? ['andReturnSelf', [], true] : null,
            $this->isName($inner->name, 'returnValueMap') => $this->mapReturnMap($inner->args),
            default => null,
        };
    }

    /**
     * Turns a PHPUnit invocation matcher into the Mockery count calls that follow `shouldReceive()`.
     * `any()` needs none; a variable or unrecognised matcher returns null, aborting the conversion.
     *
     * @return list<array{0: non-empty-string, 1: list<Arg>}>|null
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
            $this->isName($matcher->name, 'any') => [],
            $this->isName($matcher->name, 'once') => [['once', []]],
            $this->isName($matcher->name, 'never') => [['never', []]],
            $this->isName($matcher->name, 'exactly') && $value !== null => [['times', [new Arg($value)]]],
            $this->isName($matcher->name, 'atLeastOnce') => [['atLeast', []], ['once', []]],
            $this->isName($matcher->name, 'atLeast') && $value !== null => [['atLeast', []], ['times', [new Arg($value)]]],
            $this->isName($matcher->name, 'atMost') && $value !== null => [['atMost', []], ['times', [new Arg($value)]]],
            default => null,
        };
    }

    /**
     * Maps a `with()` call, translating each PHPUnit constraint to its Mockery matcher (or plain value).
     * A constraint with no faithful form aborts the whole chain, since leaving the raw `$this->…()` call
     * would break once the test loses its TestCase base.
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

            $constraint = $this->mapConstraint($arg->value);
            if ($constraint === null) {
                return null;
            }

            $mapped[] = new Arg($constraint);
        }

        return ['with', $mapped, false];
    }

    /**
     * One `with()` argument: a plain value passes through, a constraint with a dedicated Mockery matcher
     * maps onto it, and the rest become a `Mockery::on()` predicate — or null when no faithful form exists.
     */
    private function mapConstraint(Node\Expr $value): ?Node\Expr
    {
        $name = PhpunitConstraint::name($value);
        if ($name === null) {
            return $value;
        }

        $args = PhpunitConstraint::arguments($value) ?? [];
        $first = $args[0] ?? null;
        $single = \count($args) === 1;

        # `isType('integer')`, PHPUnit 12's `isInt()`, … → `Mockery::type('int')` where Mockery's own
        # `is_*()` check agrees with PHPUnit's.
        $checkedType = PhpunitConstraint::checkedType($value);
        if (\in_array($checkedType, self::MOCKERY_TYPES, true)) {
            return $this->mockery('type', [new String_($checkedType)]);
        }

        $dedicated = match ($name) {
            'anything' => $args === [] ? $this->mockery('any') : null,
            'equalTo' => $single ? $first : null,
            'identicalTo' => $single ? $this->mockery('isSame', [$first]) : null,
            'isInstanceOf' => $single ? $this->mockery('type', [$first]) : null,
            'isType' => $single && !$first instanceof String_ ? $this->mockery('type', [$first]) : null,
            'callback' => $single ? $this->mockery('on', [$first]) : null,
            'matchesRegularExpression' => $single ? $this->mockery('pattern', [$first]) : null,
            'arrayHasKey' => $single ? $this->mockery('hasKey', [$first]) : null,
            'contains', 'containsEqual' => $single ? $this->mockery('hasValue', [$first]) : null,
            'isNull' => $args === [] ? $this->mockery('isSame', [new ConstFetch(new Name('null'))]) : null,
            'isTrue' => $args === [] ? $this->mockery('isSame', [new ConstFetch(new Name('true'))]) : null,
            'isFalse' => $args === [] ? $this->mockery('isSame', [new ConstFetch(new Name('false'))]) : null,
            default => null,
        };
        if ($dedicated !== null) {
            return $dedicated;
        }

        $constraint = new PhpunitConstraint($this->predicateName);
        $predicate = $constraint->predicate($value);

        return $predicate === null ? null : $this->mockery('on', [$constraint->closure($predicate)]);
    }

    private function segmentName(MethodCall $segment): ?string
    {
        return $segment->name instanceof Identifier ? $segment->name->toString() : null;
    }

    /**
     * @param list<Arg> $args
     */
    private function mock(array $args): StaticCall
    {
        return new StaticCall(new FullyQualified('Mockery'), new Identifier('mock'), $args);
    }

    private function ignoreMissing(StaticCall $mock): MethodCall
    {
        return new MethodCall($mock, new Identifier('shouldIgnoreMissing'));
    }

    /**
     * @param list<Node\Expr> $args
     */
    private function mockery(string $method, array $args = []): StaticCall
    {
        return new StaticCall(
            new FullyQualified('Mockery'),
            new Identifier($method),
            \array_map(static fn(Node\Expr $arg): Arg => new Arg($arg), $args),
        );
    }
}
