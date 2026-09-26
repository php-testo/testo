<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Internal;

use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Token;

/**
 * Keeps the source formatting of a declaration whose last attribute group a rule removed.
 *
 * The format-preserving printer cannot empty a list: a node whose `attrGroups` went from some to
 * none is pretty-printed from scratch, which drops the blank lines of a method body or of a class.
 * Pointing the node at a copy of its original that starts past the removed attributes makes the
 * printer copy the original tokens from there, while the enclosing list still copies everything up
 * to the original start: the leading comments and indentation stay, the attribute lines go.
 *
 * @internal
 */
final class DroppedAttributes
{
    /**
     * Call after the rule has set `attrGroups`; a node that keeps any group is left alone.
     *
     * @param array<Token> $tokens The file's original tokens.
     */
    public static function keepFormat(ClassLike|ClassMethod|Function_ $node, array $tokens): void
    {
        $original = $node->getAttribute('origNode');
        if ($node->attrGroups !== [] || !$original instanceof $node || $original->attrGroups === []) {
            return;
        }

        $end = \max(\array_map(
            static fn(AttributeGroup $group): int => $group->getEndTokenPos(),
            $original->attrGroups,
        ));
        if ($end < 0) {
            return;
        }

        $start = $end + 1;
        while (isset($tokens[$start]) && $tokens[$start]->id === \T_WHITESPACE) {
            ++$start;
        }

        $trimmed = clone $original;
        $trimmed->attrGroups = [];
        $trimmed->setAttribute('startTokenPos', $start);
        $node->setAttribute('origNode', $trimmed);
    }
}
