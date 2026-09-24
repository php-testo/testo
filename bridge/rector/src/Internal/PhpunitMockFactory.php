<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Internal;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;

/**
 * What a PHPUnit mock factory call builds, read off the call so each mock rule only has to emit its own
 * library's form:
 *
 * - `$this->createMock(X)` / `createStub(X)` — a double of X answering unconfigured calls with defaults;
 * - `$this->create{Mock,Stub}ForIntersectionOfInterfaces([A, B])` — the same for several interfaces;
 * - `$this->createConfiguredMock(X, ['m' => $v])` / `createConfiguredStub` — plus a stubbed return per method;
 * - `$this->createPartialMock(X, ['m'])` — only the listed methods doubled, the rest run for real;
 * - `$this->getMockBuilder(X)->…->getMock()` with the steps `disableOriginalConstructor()`,
 *   `setConstructorArgs()`, `onlyMethods()`, `disableAutoReturnValueGeneration()`,
 *   `disableOriginalClone()`, `disableArgumentCloning()`.
 *
 * A form whose double no single target call reproduces is rejected: a non-partial double whose real
 * constructor runs (the bare `getMockBuilder(X)->getMock()`), `getMockForAbstractClass()`, `addMethods()`,
 * a builder step not listed above, or a computed method list / configuration map.
 *
 * @internal
 */
final class PhpunitMockFactory
{
    /**
     * Builder steps that change nothing a target library would reproduce differently: neither Double
     * nor Mockery clones a double or its arguments.
     */
    private const NEUTRAL_STEPS = ['disableOriginalClone', 'disableArgumentCloning'];

    /**
     * @param non-empty-list<Arg> $targets The doubled types, as the factory received them.
     * @param Array_|null $configuration `createConfiguredMock()`'s method → return map (string keys).
     * @param list<Expr>|null $partialMethods The doubled methods of a partial mock; null when every method
     *        is doubled, an empty list when none is.
     * @param Expr|null $constructorArgs The argument list the real constructor runs with (an array
     *        expression), or null when the constructor does not run. Set only for a partial mock.
     * @param bool $autoReturn False after `disableAutoReturnValueGeneration()`: an unconfigured call fails
     *        instead of returning a default.
     */
    private function __construct(
        public readonly array $targets,
        public readonly ?Array_ $configuration = null,
        public readonly ?array $partialMethods = null,
        public readonly ?Expr $constructorArgs = null,
        public readonly bool $autoReturn = true,
    ) {}

    public static function parse(MethodCall $call): ?self
    {
        $name = self::name($call);

        if ($name === 'getMock') {
            return $call->args === [] ? self::parseBuilder($call->var) : null;
        }

        if (!self::isThis($call->var)) {
            return null;
        }

        $args = self::positional($call);
        if ($args === null) {
            return null;
        }

        return match ($name) {
            'createMock', 'createStub' => \count($args) === 1 ? new self([new Arg($args[0])]) : null,
            'createMockForIntersectionOfInterfaces', 'createStubForIntersectionOfInterfaces' => self::parseIntersection($args),
            'createConfiguredMock', 'createConfiguredStub' => self::parseConfigured($args),
            'createPartialMock' => self::parsePartial($args),
            default => null,
        };
    }

    /**
     * The class a partial mock doubles — the one target a partial or configured mock has.
     */
    public function target(): Expr
    {
        return $this->targets[0]->value;
    }

    /**
     * @param list<Expr> $args
     */
    private static function parseIntersection(array $args): ?self
    {
        $list = \count($args) === 1 ? self::listItems($args[0]) : null;

        return $list === null || $list === [] ? null : new self(\array_map(static fn(Expr $item): Arg => new Arg($item), $list));
    }

    /**
     * @param list<Expr> $args
     */
    private static function parseConfigured(array $args): ?self
    {
        if (\count($args) !== 2 || !$args[1] instanceof Array_ || $args[1]->items === []) {
            return null;
        }

        foreach ($args[1]->items as $item) {
            if ($item === null || !$item->key instanceof String_ || $item->unpack || $item->byRef) {
                return null;
            }
        }

        return new self([new Arg($args[0])], configuration: $args[1]);
    }

    /**
     * @param list<Expr> $args
     */
    private static function parsePartial(array $args): ?self
    {
        $methods = \count($args) === 2 ? self::listItems($args[1]) : null;

        return $methods === null ? null : new self([new Arg($args[0])], partialMethods: $methods);
    }

    /**
     * Walks a builder chain down from its `getMock()` to `$this->getMockBuilder(X)`.
     */
    private static function parseBuilder(Expr $cursor): ?self
    {
        $steps = [];
        while ($cursor instanceof MethodCall) {
            $name = self::name($cursor);
            if ($name === null || isset($steps[$name])) {
                return null;
            }

            if ($name === 'getMockBuilder') {
                if (!self::isThis($cursor->var)) {
                    return null;
                }

                $target = self::positional($cursor);

                return $target !== null && \count($target) === 1 ? self::fromSteps(new Arg($target[0]), $steps) : null;
            }

            $args = self::positional($cursor);
            if ($args === null) {
                return null;
            }

            $steps[$name] = $args;
            $cursor = $cursor->var;
        }

        return null;
    }

    /**
     * @param array<string, list<Expr>> $steps
     */
    private static function fromSteps(Arg $target, array $steps): ?self
    {
        $disabled = false;
        $methods = null;
        $constructorArgs = null;
        $autoReturn = true;

        foreach ($steps as $name => $args) {
            switch ($name) {
                case 'disableOriginalConstructor':
                    $disabled = true;
                    $valid = $args === [];
                    break;
                case 'disableAutoReturnValueGeneration':
                    $autoReturn = false;
                    $valid = $args === [];
                    break;
                case 'onlyMethods':
                    $methods = \count($args) === 1 ? self::listItems($args[0]) : null;
                    $valid = $methods !== null;
                    break;
                case 'setConstructorArgs':
                    $constructorArgs = $args[0] ?? null;
                    $valid = \count($args) === 1;
                    break;
                default:
                    $valid = $args === [] && \in_array($name, self::NEUTRAL_STEPS, true);
            }

            if (!$valid) {
                return null;
            }
        }

        if ($disabled && $constructorArgs !== null) {
            return null;
        }

        # A builder that keeps the constructor runs it — with no arguments unless told otherwise.
        $constructorArgs ??= $disabled ? null : new Array_([]);

        # Only a partial double keeps real behaviour a constructor's state could feed; a full double
        # whose constructor runs has no single-call equivalent.
        if ($methods === null && $constructorArgs !== null) {
            return null;
        }

        return new self([$target], partialMethods: $methods, constructorArgs: $constructorArgs, autoReturn: $autoReturn);
    }

    /**
     * The values of a plain list literal, or null for anything else.
     *
     * @return list<Expr>|null
     */
    private static function listItems(Expr $value): ?array
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
     * @return list<Expr>|null
     */
    private static function positional(MethodCall $call): ?array
    {
        $args = [];
        foreach ($call->args as $arg) {
            if (!$arg instanceof Arg || $arg->name !== null || $arg->unpack) {
                return null;
            }
            $args[] = $arg->value;
        }

        return $args;
    }

    private static function isThis(Expr $expr): bool
    {
        return $expr instanceof Variable && $expr->name === 'this';
    }

    private static function name(MethodCall $call): ?string
    {
        return $call->name instanceof Identifier ? $call->name->toString() : null;
    }
}
