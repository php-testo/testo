<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Internal;

use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use Rector\NodeTypeResolver\Node\AttributeKey;

/**
 * Names the parameter of a predicate closure a rule synthesises inside a statement
 * (`fn ($value) => $value > $limit`): `value`, or the first `valueN` that neither the statement's scope
 * nor the statement itself already uses, so the parameter never shadows a variable the predicate reads.
 *
 * @internal
 */
final class PredicateVariable
{
    public static function nameFor(Expression $statement): string
    {
        $used = [];
        foreach ((new NodeFinder())->findInstanceOf($statement->expr, Variable::class) as $variable) {
            if (\is_string($variable->name)) {
                $used[$variable->name] = true;
            }
        }

        $scope = $statement->getAttribute(AttributeKey::SCOPE);
        $name = 'value';
        $suffix = 1;
        while (isset($used[$name]) || ($scope instanceof Scope && $scope->hasVariableType($name)->yes())) {
            $name = 'value' . (++$suffix);
        }

        return $name;
    }
}
