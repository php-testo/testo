<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Internal;

use PhpParser\Node\Arg;
use PhpParser\Node\VariadicPlaceholder;

/**
 * Binds call or attribute arguments to their parameter positions, so a rule can read a named
 * argument as if it were written positionally.
 *
 * @internal
 */
final class PositionalArgs
{
    /**
     * The arguments in parameter order, named ones stripped of their names. Null when the binding
     * cannot be resolved: an unpacked argument, a first-class callable, a name the declaration lacks
     * (names are case-sensitive), or a missing argument before a bound one.
     *
     * @param array<Arg|VariadicPlaceholder> $args
     * @param list<string>|\Closure(): (list<string>|null) $parameters The parameter names, or a lazy
     *        source of them, read only when a named argument occurs; null means they are unknown.
     * @return list<Arg>|null
     */
    public static function bind(array $args, array|\Closure $parameters): ?array
    {
        $bound = self::byPosition($args, $parameters);

        return $bound !== null && \array_is_list($bound) ? $bound : null;
    }

    /**
     * Like {@see bind()}, but a skipped optional parameter leaves a gap instead of failing, for a
     * caller that reads only some positions.
     *
     * @param array<Arg|VariadicPlaceholder> $args
     * @param list<string>|\Closure(): (list<string>|null) $parameters
     * @return array<int, Arg>|null Keyed by parameter position, in ascending order.
     */
    public static function byPosition(array $args, array|\Closure $parameters): ?array
    {
        $bound = [];
        $position = 0;
        foreach ($args as $arg) {
            if (!$arg instanceof Arg || $arg->unpack) {
                return null;
            }

            if ($arg->name === null) {
                # A positional argument after a named one; PHP rejects it, a synthesized node may not.
                if (\count($bound) !== $position) {
                    return null;
                }

                $bound[$position++] = $arg;
                continue;
            }

            $parameters instanceof \Closure and $parameters = $parameters() ?? [];
            $index = \array_search($arg->name->toString(), $parameters, true);
            if (!\is_int($index) || isset($bound[$index])) {
                return null;
            }

            $bound[$index] = new Arg($arg->value, $arg->byRef, attributes: $arg->getAttributes());
        }

        \ksort($bound);

        return $bound;
    }
}
