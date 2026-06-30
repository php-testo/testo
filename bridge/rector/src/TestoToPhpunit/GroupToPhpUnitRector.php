<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name\FullyQualified;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Expands a variadic Testo `#[\Testo\Filter\Group('a', 'b', …)]` into the repeated, single-name
 * PHPUnit `#[\PHPUnit\Framework\Attributes\Group]` form (`#[Group('a'), Group('b')]`).
 *
 * The arity differs: Testo's `Group` is variadic and not repeatable; PHPUnit's takes one name and
 * is repeatable. So one Testo attribute becomes N PHPUnit attributes (one per name), in order.
 *
 * Inheritance is handled by a complementary rule: Testo computes a test's effective groups as the
 * union over its method, class, parent classes and traits. This rule rewrites each `Group` attribute
 * where it is written, per-node; {@see GroupInheritanceToPhpUnitRector} walks the class hierarchy and
 * flattens that inherited union onto the leaf test class. The two coexist in any order (Rector
 * iterates to a fixed point) and neither duplicates a group the other already produced. See TODO.md.
 */
#[TestRectorFixtures('GroupToPhpUnitRector')]
final class GroupToPhpUnitRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Expand variadic Testo #[\Testo\Filter\Group] into repeated PHPUnit #[Group] attributes',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\Testo\Filter\Group('db', 'slow')]
                        PHP,
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\Group('db'), \PHPUnit\Framework\Attributes\Group('slow')]
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [AttributeGroup::class];
    }

    /**
     * @param AttributeGroup $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        $attrs = [];
        $changed = false;

        foreach ($node->attrs as $attr) {
            if (!$this->isName($attr->name, 'Testo\\Filter\\Group')) {
                $attrs[] = $attr;
                continue;
            }

            foreach ($attr->args as $arg) {
                $arg instanceof Arg and $attrs[] = new Attribute(
                    new FullyQualified('PHPUnit\\Framework\\Attributes\\Group'),
                    [new Arg(clone $arg->value)],
                );
            }
            $changed = true;
        }

        # Bail on a degenerate `#[Group()]` (no names) that would leave the group empty.
        if (!$changed || $attrs === []) {
            return null;
        }

        $node->attrs = $attrs;

        return $node;
    }
}
