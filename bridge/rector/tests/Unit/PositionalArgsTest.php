<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Unit;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\VariadicPlaceholder;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Testo\Assert;
use Testo\Bridge\Rector\Internal\PositionalArgs;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Test;

/**
 * Arguments bind to the positions of the parameters `a`, `b`, `c`, named ones by their name, and the
 * binding fails closed on anything it cannot place.
 */
#[Test]
#[Covers(PositionalArgs::class)]
final class PositionalArgsTest
{
    private const PARAMETERS = ['a', 'b', 'c'];

    #[DataSet(['f(1, 2, 3)', ['1', '2', '3']], 'positional')]
    #[DataSet(['f(c: 3, a: 1, b: 2)', ['1', '2', '3']], 'named in another order')]
    #[DataSet(['f(1, c: 3, b: 2)', ['1', '2', '3']], 'mixed')]
    #[DataSet(['f(1, 2, 3, 4)', ['1', '2', '3', '4']], 'extra positional argument')]
    #[DataSet(['f()', []], 'no arguments')]
    #[DataSet(['f(1, a: 2)', null], 'name bound twice')]
    #[DataSet(['f(d: 1)', null], 'unknown name')]
    #[DataSet(['f(A: 1)', null], 'names are case-sensitive')]
    #[DataSet(['f(a: 1, c: 3)', null], 'missing argument before a bound one')]
    #[DataSet(['f(...$args)', null], 'unpacked argument')]
    #[DataSet(['f(...)', null], 'first-class callable')]
    public function binds(string $call, ?array $expected): void
    {
        $bound = PositionalArgs::bind(self::args($call), self::PARAMETERS);

        Assert::same(self::print($bound), $expected);
    }

    public function positionalArgumentAfterNamedFails(): void
    {
        $args = self::args('f(1, 2)');
        $args[0]->name = new Identifier('b');

        Assert::null(PositionalArgs::bind($args, self::PARAMETERS));
    }

    public function namedArgumentLosesItsName(): void
    {
        $bound = PositionalArgs::bind(self::args('f(b: 2, a: 1)'), self::PARAMETERS);
        Assert::notNull($bound);

        foreach ($bound as $arg) {
            Assert::null($arg->name);
        }
    }

    #[DataSet(['f(a: 1, c: 3)', [0 => '1', 2 => '3']], 'skipped optional parameter leaves a gap')]
    #[DataSet(['f(c: 3)', [2 => '3']], 'only a later parameter')]
    #[DataSet(['f(d: 1)', null], 'unknown name')]
    public function bindsByPosition(string $call, ?array $expected): void
    {
        $bound = PositionalArgs::byPosition(self::args($call), self::PARAMETERS);

        Assert::same(self::print($bound), $expected);
    }

    #[DataSet(['f(1, 2)', 0], 'positional only')]
    #[DataSet(['f(a: 1, b: 2)', 1], 'named')]
    public function readsLazyParametersOnlyForNamedArguments(string $call, int $reads): void
    {
        $count = 0;
        $parameters = static function () use (&$count): array {
            ++$count;
            return self::PARAMETERS;
        };

        Assert::notNull(PositionalArgs::bind(self::args($call), $parameters));
        Assert::same($count, $reads);
    }

    public function unknownLazyParametersFailNamedArguments(): void
    {
        Assert::null(PositionalArgs::bind(self::args('f(a: 1)'), static fn(): ?array => null));
    }

    /**
     * @return array<Arg|VariadicPlaceholder>
     */
    private static function args(string $call): array
    {
        $statement = (new ParserFactory())->createForNewestSupportedVersion()->parse("<?php {$call};")[0];
        \assert($statement instanceof Expression && $statement->expr instanceof FuncCall);

        return $statement->expr->args;
    }

    /**
     * @param array<int, Arg>|null $args
     * @return array<int, string>|null
     */
    private static function print(?array $args): ?array
    {
        return $args === null
            ? null
            : \array_map(static fn(Arg $arg): string => (new Standard())->prettyPrintExpr($arg->value), $args);
    }
}
