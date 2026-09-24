<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Internal;

use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Translates PHPUnit argument constraints (`$this->greaterThan(5)`, `self::logicalOr(...)`, …) into
 * plain PHP boolean expressions over one variable, for the mock rules that target a library with no
 * dedicated matcher for the constraint: Double's `Argument::satisfies()`, Mockery's `Mockery::on()`.
 *
 * Each expression reproduces the constraint's own evaluation (`IsEqual` compares with `==`,
 * `ArrayHasKey` also accepts `ArrayAccess`, `IsEmpty` counts a `Countable`, …). A constraint with no
 * faithful expression yields null, and the caller leaves the whole chain for manual migration.
 *
 * @internal
 */
final class PhpunitConstraint
{
    /**
     * PHPUnit `isType()` names → the canonical PHP type they check. `resource (closed)` has no `is_*()`
     * function and is checked through `gettype()`.
     */
    private const NATIVE_TYPES = [
        'array' => 'array',
        'bool' => 'bool',
        'boolean' => 'bool',
        'callable' => 'callable',
        'double' => 'float',
        'float' => 'float',
        'int' => 'int',
        'integer' => 'int',
        'iterable' => 'iterable',
        'null' => 'null',
        'numeric' => 'numeric',
        'object' => 'object',
        'real' => 'float',
        'resource' => 'resource',
        'resource (closed)' => 'resource (closed)',
        'closed resource' => 'resource (closed)',
        'scalar' => 'scalar',
        'string' => 'string',
    ];

    /**
     * The typed factories PHPUnit 12 added in place of `isType()` and `containsOnly()`, by the PHP type
     * they check.
     */
    private const TYPE_FACTORIES = [
        'Array' => 'array',
        'Bool' => 'bool',
        'Callable' => 'callable',
        'Float' => 'float',
        'Int' => 'int',
        'Iterable' => 'iterable',
        'Null' => 'null',
        'Numeric' => 'numeric',
        'Object' => 'object',
        'Resource' => 'resource',
        'ClosedResource' => 'resource (closed)',
        'Scalar' => 'scalar',
        'String' => 'string',
    ];

    /**
     * Constraints that are a plain `is_*()`-style function applied to the value.
     */
    private const VALUE_FUNCTIONS = [
        'isNan' => 'is_nan',
        'isFinite' => 'is_finite',
        'isInfinite' => 'is_infinite',
        'isReadable' => 'is_readable',
        'isWritable' => 'is_writable',
        'fileExists' => 'file_exists',
        'directoryExists' => 'is_dir',
    ];

    /**
     * @param non-empty-string $variable The predicate parameter the expressions are written over.
     */
    public function __construct(
        private readonly string $variable,
    ) {}

    /**
     * True for a PHPUnit constraint factory call — `$this->equalTo(...)` or the `self::`/`static::`
     * forms — as opposed to a plain value.
     */
    public static function isConstraintCall(Expr $value): bool
    {
        if ($value instanceof MethodCall) {
            return $value->var instanceof Variable && $value->var->name === 'this' && $value->name instanceof Identifier;
        }

        return $value instanceof StaticCall
            && $value->class instanceof Name
            && \in_array(\strtolower($value->class->toString()), ['self', 'static'], true)
            && $value->name instanceof Identifier;
    }

    /**
     * The constraint's factory name (`greaterThan`), or null for anything that is not a constraint call.
     */
    public static function name(Expr $value): ?string
    {
        if (!self::isConstraintCall($value)) {
            return null;
        }

        \assert($value instanceof MethodCall || $value instanceof StaticCall);
        \assert($value->name instanceof Identifier);

        return $value->name->toString();
    }

    /**
     * The constraint's arguments when all are plain and positional, null otherwise.
     *
     * @return list<Expr>|null
     */
    public static function arguments(Expr $value): ?array
    {
        if (!$value instanceof MethodCall && !$value instanceof StaticCall) {
            return null;
        }

        $args = [];
        foreach ($value->args as $arg) {
            if (!$arg instanceof Arg || $arg->name !== null || $arg->unpack) {
                return null;
            }
            $args[] = $arg->value;
        }

        return $args;
    }

    /**
     * Canonical PHP type for a PHPUnit `isType()` name: `int`, `float`, `bool`, `string`, `array`,
     * `object`, `callable`, `iterable`, `null`, `numeric`, `scalar`, `resource` or `resource (closed)`.
     */
    public static function nativeType(string $name): ?string
    {
        return self::NATIVE_TYPES[\strtolower($name)] ?? null;
    }

    /**
     * The PHP type a type-checking constraint checks — `isType('integer')`, or PHPUnit 12's `isInt()` —
     * or null when $constraint is not one with a literal type.
     */
    public static function checkedType(Expr $constraint): ?string
    {
        $name = self::name($constraint);
        $args = self::arguments($constraint);
        if ($name === null || $args === null) {
            return null;
        }

        if ($name === 'isType') {
            return \count($args) === 1 && $args[0] instanceof String_ ? self::nativeType($args[0]->value) : null;
        }

        # `isNull()` is its own constraint (`IsNull`), not a type factory.
        return $args === [] && $name !== 'isNull' && \str_starts_with($name, 'is') ? self::TYPE_FACTORIES[\substr($name, 2)] ?? null : null;
    }

    /**
     * A deep copy of $expr, for an expression that has to appear twice in the output: one node object
     * in two tree positions confuses the printer's and Rector's per-node bookkeeping. The copy carries no
     * attributes — an inherited position or original-node link would make the format-preserving printer
     * try to reuse source text the copy does not own.
     *
     * @template T of Expr
     * @param T $expr
     * @return T
     */
    public static function copy(Expr $expr): Expr
    {
        $cloner = new class extends NodeVisitorAbstract {
            public function enterNode(Node $node): Node
            {
                $copy = clone $node;
                $copy->setAttributes([]);

                return $copy;
            }
        };

        [$copy] = (new NodeTraverser($cloner))->traverse([$expr]);
        \assert($copy instanceof $expr);

        return $copy;
    }

    /**
     * The constraint as a boolean expression over the predicate variable, or null when it has none. A
     * plain value is PHPUnit's implicit `equalTo()`.
     */
    public function predicate(Expr $constraint): ?Expr
    {
        $value = $this->value();
        $name = self::name($constraint);
        if ($name === null) {
            return new BinaryOp\Equal($value, $constraint);
        }

        $args = self::arguments($constraint);
        if ($args === null) {
            return null;
        }

        $count = \count($args);
        $first = $args[0] ?? null;

        if (isset(self::VALUE_FUNCTIONS[$name])) {
            return $count === 0 ? $this->valueFunction($name) : null;
        }

        $checkedType = self::checkedType($constraint);
        if ($checkedType !== null) {
            return $this->nativeCheck($value, $checkedType);
        }

        if (\str_starts_with($name, 'containsOnly') && isset(self::TYPE_FACTORIES[\substr($name, 12)])) {
            $item = new Variable($this->variable . 'Item');

            return $count === 0 ? $this->allItems($item, $this->nativeCheck($item, self::TYPE_FACTORIES[\substr($name, 12)])) : null;
        }

        return match ($name) {
            'anything' => $count === 0 ? $this->constant('true') : null,
            'equalTo' => $count === 1 ? new BinaryOp\Equal($value, $first) : null,
            'identicalTo' => $count === 1 ? new BinaryOp\Identical($value, $first) : null,
            'isNull' => $count === 0 ? new BinaryOp\Identical($value, $this->constant('null')) : null,
            'isTrue' => $count === 0 ? new BinaryOp\Identical($value, $this->constant('true')) : null,
            'isFalse' => $count === 0 ? new BinaryOp\Identical($value, $this->constant('false')) : null,
            'greaterThan' => $count === 1 ? new BinaryOp\Greater($value, $first) : null,
            'lessThan' => $count === 1 ? new BinaryOp\Smaller($value, $first) : null,
            'greaterThanOrEqual' => $count === 1 ? new BinaryOp\GreaterOrEqual($value, $first) : null,
            'lessThanOrEqual' => $count === 1 ? new BinaryOp\SmallerOrEqual($value, $first) : null,
            'equalToWithDelta' => $count === 2 ? $this->withinDelta($first, $args[1]) : null,
            'equalToIgnoringCase' => $count === 1 ? $this->equalIgnoringCase($first) : null,
            'equalToCanonicalizing' => $count === 1 ? $this->equalCanonicalizing($first) : null,
            'isEmpty' => $count === 0 ? $this->isEmpty() : null,
            'isList' => $count === 0 ? new BinaryOp\BooleanAnd($this->func('is_array', [$value]), $this->func('array_is_list', [$value])) : null,
            'isJson' => $count === 0 ? $this->isJson() : null,
            'countOf' => $count === 1 ? new BinaryOp\BooleanAnd($this->func('is_countable', [$value]), new BinaryOp\Identical($this->func('count', [$value]), $first)) : null,
            'stringContains' => $this->stringContains($args),
            'stringStartsWith' => $count === 1 ? $this->onString($this->func('str_starts_with', [$value, $first])) : null,
            'stringEndsWith' => $count === 1 ? $this->onString($this->func('str_ends_with', [$value, $first])) : null,
            'matchesRegularExpression' => $count === 1 ? $this->onString(new BinaryOp\Greater($this->func('preg_match', [$first, $value]), new Int_(0))) : null,
            'arrayHasKey' => $count === 1 ? $this->hasKey($first) : null,
            'contains', 'containsEqual' => $count === 1 ? $this->inIterable($first, false) : null,
            'containsIdentical' => $count === 1 ? $this->inIterable($first, true) : null,
            'containsOnly' => $count === 1 ? $this->containsOnly($first) : null,
            'containsOnlyInstancesOf' => $count === 1 ? $this->containsOnlyInstancesOf($first) : null,
            'isInstanceOf' => $count === 1 ? $this->instanceOf($value, $first) : null,
            'isType' => $count === 1 ? $this->typeCheck($value, $first) : null,
            'objectEquals' => $this->objectEquals($args),
            'callback' => $count === 1 ? new FuncCall($first, [new Arg($value)]) : null,
            'logicalNot' => $count === 1 ? $this->negate($first) : null,
            'logicalOr' => $this->combine($args, BinaryOp\BooleanOr::class),
            'logicalAnd' => $this->combine($args, BinaryOp\BooleanAnd::class),
            'logicalXor' => $this->combine($args, BinaryOp\LogicalXor::class),
            default => null,
        };
    }

    /**
     * `fn ($value) => <predicate>`, the closure both target libraries take.
     */
    public function closure(Expr $predicate): ArrowFunction
    {
        return new ArrowFunction(['params' => [new Param($this->value())], 'expr' => $predicate]);
    }

    /**
     * `isType('int')` / `isInstanceOf(X::class)` / `containsOnly(...)` as a check on $subject: an
     * `is_*()` call for a PHP type, `instanceof` for a class. A computed name could be either and has
     * no single form.
     */
    public function typeCheck(Expr $subject, Expr $type): ?Expr
    {
        if (!$type instanceof String_) {
            return null;
        }

        $native = self::nativeType($type->value);

        return $native === null ? null : $this->nativeCheck($subject, $native);
    }

    /**
     * `is_<type>($subject)`. A resource is checked through `gettype()`: PHPUnit's `resource` also
     * accepts a closed one, which `is_resource()` rejects, and `resource (closed)` has no `is_*()`.
     */
    private function nativeCheck(Expr $subject, string $native): Expr
    {
        $closed = new BinaryOp\Identical($this->func('gettype', [$subject]), new String_('resource (closed)'));

        return match ($native) {
            'resource (closed)' => $closed,
            'resource' => new BinaryOp\BooleanOr($this->func('is_resource', [$subject]), $closed),
            default => $this->func('is_' . $native, [$subject]),
        };
    }

    /**
     * The math and filesystem checks, behind the type guard PHPUnit applies first: a number for
     * `isNan()`/`isFinite()`/`isInfinite()`, a string path for the file constraints.
     */
    private function valueFunction(string $name): Expr
    {
        $check = $this->func(self::VALUE_FUNCTIONS[$name], [$this->value()]);

        if (\in_array($name, ['isNan', 'isFinite', 'isInfinite'], true)) {
            return new BinaryOp\BooleanAnd(
                new BinaryOp\BooleanOr($this->func('is_float', [$this->value()]), $this->func('is_int', [$this->value()])),
                $check,
            );
        }

        return $this->onString($check);
    }

    private function instanceOf(Expr $subject, Expr $class): Instanceof_
    {
        $target = $class instanceof ClassConstFetch && $class->class instanceof Name
            && $class->name instanceof Identifier && \strtolower($class->name->toString()) === 'class'
                ? $class->class
                : $class;

        return new Instanceof_($subject, $target);
    }

    /**
     * `IsEqualWithDelta`: a number within `$delta` of the expected one; any other value is compared
     * with plain `==`, as PHPUnit's comparator falls back to.
     */
    private function withinDelta(Expr $expected, Expr $delta): Expr
    {
        return new Ternary(
            $this->func('is_numeric', [$this->value()]),
            new BinaryOp\SmallerOrEqual(
                $this->func('abs', [new BinaryOp\Minus($this->value(), $expected)]),
                $delta,
            ),
            new BinaryOp\Equal($this->value(), self::copy($expected)),
        );
    }

    /**
     * `IsEqualIgnoringCase`: a boolean compares with plain `==`; a scalar or null is lowercased as a
     * string on both sides first, as PHPUnit's scalar comparator does.
     */
    private function equalIgnoringCase(Expr $expected): Expr
    {
        return new Ternary(
            $this->func('is_bool', [$this->value()]),
            new BinaryOp\Equal($this->value(), $expected),
            new BinaryOp\BooleanAnd(
                new BinaryOp\BooleanOr(
                    $this->func('is_scalar', [$this->value()]),
                    new BinaryOp\Identical($this->value(), $this->constant('null')),
                ),
                new BinaryOp\Equal(
                    $this->func('mb_strtolower', [new Cast\String_($this->value())]),
                    $this->func('mb_strtolower', [new Cast\String_(self::copy($expected))]),
                ),
            ),
        );
    }

    /**
     * `IsEqualCanonicalizing`: two arrays are sorted before the `==` comparison, an array never equals a
     * non-array, and two scalars compare as is. The sort needs statements, so it runs in an immediately
     * invoked static closure.
     */
    private function equalCanonicalizing(Expr $expected): Expr
    {
        $a = new Variable('actual');
        $b = new Variable('expected');
        $sorter = new Closure([
            'static' => true,
            'params' => [new Param($a), new Param($b)],
            'returnType' => new Identifier('bool'),
            'stmts' => [
                new If_(new BinaryOp\NotIdentical($this->func('is_array', [$a]), $this->func('is_array', [$b])), [
                    'stmts' => [new Return_($this->constant('false'))],
                ]),
                new If_($this->func('is_array', [$a]), [
                    'stmts' => [
                        new Expression($this->func('sort', [$a])),
                        new Expression($this->func('sort', [$b])),
                    ],
                ]),
                new Return_(new BinaryOp\Equal($a, $b)),
            ],
        ]);

        return new FuncCall($sorter, [new Arg($this->value()), new Arg($expected)]);
    }

    /**
     * `IsEmpty`: a `Countable` counts, anything else goes through `empty()`.
     */
    private function isEmpty(): Expr
    {
        return new Ternary(
            new Instanceof_($this->value(), new FullyQualified('Countable')),
            new BinaryOp\Identical($this->func('count', [$this->value()]), new Int_(0)),
            new Empty_($this->value()),
        );
    }

    /**
     * `IsJson`: a string that decodes without error (`'null'` included, `''` excluded).
     */
    private function isJson(): Expr
    {
        return new BinaryOp\BooleanAnd(
            $this->func('is_string', [$this->value()]),
            new BinaryOp\BooleanOr(
                new BinaryOp\NotIdentical($this->func('json_decode', [$this->value()]), $this->constant('null')),
                new BinaryOp\Identical($this->func('json_last_error', []), new ConstFetch(new Name('JSON_ERROR_NONE'))),
            ),
        );
    }

    /**
     * `stringContains($needle)` → `str_contains()`; with a literal `true` case flag →
     * `mb_stripos() !== false`, which is what PHPUnit runs. A computed flag or the line-ending flag
     * has no fixed form.
     *
     * @param list<Expr> $args
     */
    private function stringContains(array $args): ?Expr
    {
        $needle = $args[0] ?? null;
        if ($needle === null || \count($args) > 3) {
            return null;
        }

        if (isset($args[2]) && !$this->isConstant($args[2], 'false')) {
            return null;
        }

        # An empty needle is found in anything, string or not.
        if ($needle instanceof String_ && $needle->value === '') {
            return $this->constant('true');
        }

        $ignoreCase = $args[1] ?? $this->constant('false');
        if ($this->isConstant($ignoreCase, 'false')) {
            return $this->onString($this->func('str_contains', [$this->value(), $needle]));
        }

        if ($this->isConstant($ignoreCase, 'true')) {
            return $this->onString(new BinaryOp\NotIdentical($this->func('mb_stripos', [$this->value(), $needle]), $this->constant('false')));
        }

        return null;
    }

    /**
     * `is_string($value) && <check>`: PHPUnit's string constraints reject any other value up front.
     */
    private function onString(Expr $check): Expr
    {
        return new BinaryOp\BooleanAnd($this->func('is_string', [$this->value()]), $check);
    }

    /**
     * `ArrayHasKey`: `array_key_exists()` on an array, `offsetExists()` on an `ArrayAccess`.
     */
    private function hasKey(Expr $key): Expr
    {
        return new Ternary(
            $this->func('is_array', [$this->value()]),
            $this->func('array_key_exists', [$key, $this->value()]),
            new BinaryOp\BooleanAnd(
                new Instanceof_($this->value(), new FullyQualified('ArrayAccess')),
                new MethodCall($this->value(), new Identifier('offsetExists'), [new Arg(self::copy($key))]),
            ),
        );
    }

    /**
     * `TraversableContainsEqual` / `…Identical`: `in_array()` over the iterable's values.
     */
    private function inIterable(Expr $needle, bool $strict): Expr
    {
        $args = [new Arg($needle), new Arg($this->spread())];
        if ($strict) {
            $args[] = new Arg($this->constant('true'));
        }

        return new BinaryOp\BooleanAnd(
            $this->func('is_iterable', [$this->value()]),
            new FuncCall(new Name('in_array'), $args),
        );
    }

    /**
     * `containsOnly('int')` → every item passes `is_int()`; a literal class name → every item is an
     * instance. A computed name could be either.
     */
    private function containsOnly(Expr $type): ?Expr
    {
        $item = new Variable($this->variable . 'Item');
        $check = match (true) {
            $type instanceof String_ && self::nativeType($type->value) !== null => $this->typeCheck($item, $type),
            $type instanceof String_, $type instanceof ClassConstFetch => $this->instanceOf($item, $type),
            default => null,
        };

        return $check === null ? null : $this->allItems($item, $check);
    }

    private function containsOnlyInstancesOf(Expr $class): Expr
    {
        $item = new Variable($this->variable . 'Item');

        return $this->allItems($item, $this->instanceOf($item, $class));
    }

    /**
     * `is_iterable($value) && array_filter([...$value], fn ($item) => !<check>) === []`.
     */
    private function allItems(Variable $item, Expr $check): Expr
    {
        $rejects = new ArrowFunction(['params' => [new Param($item)], 'expr' => new BooleanNot($check)]);

        return new BinaryOp\BooleanAnd(
            $this->func('is_iterable', [$this->value()]),
            new BinaryOp\Identical(
                new FuncCall(new Name('array_filter'), [new Arg($this->spread()), new Arg($rejects)]),
                new Array_([]),
            ),
        );
    }

    /**
     * `objectEquals($expected, 'method')`: the value's own comparison method, `equals` by default.
     *
     * @param list<Expr> $args
     */
    private function objectEquals(array $args): ?Expr
    {
        $expected = $args[0] ?? null;
        $method = $args[1] ?? new String_('equals');
        if ($expected === null || \count($args) > 2 || !$method instanceof String_) {
            return null;
        }

        return new BinaryOp\BooleanAnd(
            $this->func('is_object', [$this->value()]),
            new MethodCall($this->value(), new Identifier($method->value), [new Arg($expected)]),
        );
    }

    private function negate(Expr $inner): ?BooleanNot
    {
        $predicate = $this->predicate($inner);

        return $predicate === null ? null : new BooleanNot($predicate);
    }

    /**
     * @param list<Expr> $args
     * @param class-string<BinaryOp\BooleanOr|BinaryOp\BooleanAnd|BinaryOp\LogicalXor> $operator
     */
    private function combine(array $args, string $operator): ?Expr
    {
        $combined = null;
        foreach ($args as $arg) {
            $predicate = $this->predicate($arg);
            if ($predicate === null) {
                return null;
            }

            $combined = $combined === null ? $predicate : new $operator($combined, $predicate);
        }

        return $combined;
    }

    private function spread(): Array_
    {
        return new Array_([new ArrayItem($this->value(), unpack: true)]);
    }

    private function value(): Variable
    {
        return new Variable($this->variable);
    }

    private function constant(string $name): ConstFetch
    {
        return new ConstFetch(new Name($name));
    }

    private function isConstant(Expr $expr, string $name): bool
    {
        return $expr instanceof ConstFetch && \strtolower($expr->name->toString()) === $name;
    }

    /**
     * @param list<Expr> $args
     */
    private function func(string $name, array $args): FuncCall
    {
        return new FuncCall(new Name($name), \array_map(static fn(Expr $arg): Arg => new Arg($arg), $args));
    }
}
