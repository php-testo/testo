<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Internal;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\NodeFinder;

/**
 * PHPUnit's `willReturnMap($map)` as a resolver closure for a library with no map verb:
 *
 *     fn (...$args) => array_column(array_filter($map, fn ($row) => array_slice($row, 0, -1) === $args), count($args))[0] ?? null
 *
 * That is `ReturnValueMap`'s own lookup: a row matches when everything but its last element is
 * identical (`===`) to the call's arguments, the first matching row's last element is returned, and no
 * match returns null. The map is re-read on every call, as a literal or an unchanged variable is.
 *
 * @internal
 */
final class ReturnValueMap
{
    /**
     * The resolver for $map, or null when $map reads a variable named like one of the resolver's own
     * parameters (`$args`, `$row`) — the closure would shadow it.
     */
    public static function resolver(Expr $map): ?ArrowFunction
    {
        foreach ((new NodeFinder())->findInstanceOf($map, Variable::class) as $variable) {
            if ($variable->name === 'args' || $variable->name === 'row') {
                return null;
            }
        }

        $args = new Variable('args');
        $row = new Variable('row');
        $matches = new ArrowFunction([
            'params' => [new Param($row)],
            'expr' => new BinaryOp\Identical(
                self::func('array_slice', [new Variable('row'), new Int_(0), new UnaryMinus(new Int_(1))]),
                new Variable('args'),
            ),
        ]);

        $lookup = new ArrayDimFetch(
            self::func('array_column', [
                self::func('array_filter', [$map, $matches]),
                self::func('count', [new Variable('args')]),
            ]),
            new Int_(0),
        );

        return new ArrowFunction([
            'params' => [new Param($args, variadic: true)],
            'expr' => new BinaryOp\Coalesce($lookup, new ConstFetch(new Name('null'))),
        ]);
    }

    /**
     * @param list<Expr> $args
     */
    private static function func(string $name, array $args): FuncCall
    {
        return new FuncCall(new Name($name), \array_map(static fn(Expr $arg): Arg => new Arg($arg), $args));
    }
}
