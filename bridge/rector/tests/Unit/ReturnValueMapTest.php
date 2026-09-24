<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Unit;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Expression;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Testo\Assert;
use Testo\Bridge\Rector\Internal\ReturnValueMap;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Test;

/**
 * The resolver must answer the way PHPUnit's `ReturnValueMap` does: the first row whose leading
 * elements are identical to the call's arguments gives its last element, anything else gives null.
 */
#[Test]
#[Covers(ReturnValueMap::class)]
final class ReturnValueMapTest
{
    private const MAP = "[['a', 1, 'first'], ['a', 1, 'second'], ['b', 'B'], [null, 'N']]";

    #[DataSet([['a', 1], 'first'], 'first matching row wins')]
    #[DataSet([['b'], 'B'], 'row of another length')]
    #[DataSet([[null], 'N'], 'null argument')]
    #[DataSet([['a', '1'], null], 'arguments compare strictly')]
    #[DataSet([['a'], null], 'argument count must match')]
    #[DataSet([[], null], 'no arguments')]
    #[DataSet([['c'], null], 'no matching row')]
    public function resolvesLikeReturnValueMap(array $arguments, mixed $expected): void
    {
        $resolver = ReturnValueMap::resolver(self::parse(self::MAP));
        Assert::notNull($resolver);

        $closure = eval('return ' . (new Standard())->prettyPrintExpr($resolver) . ';');

        Assert::same($closure(...$arguments), $expected);
    }

    #[DataSet(['$args'], 'map named like the variadic parameter')]
    #[DataSet(['[[$row, 1]]'], 'map reading a variable named like the row parameter')]
    public function mapShadowedByTheResolverHasNoResolver(string $map): void
    {
        Assert::null(ReturnValueMap::resolver(self::parse($map)));
    }

    private static function parse(string $code): Expr
    {
        $statement = (new ParserFactory())->createForNewestSupportedVersion()->parse("<?php {$code};")[0];
        \assert($statement instanceof Expression);

        return $statement->expr;
    }
}
