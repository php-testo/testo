<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * STUB — not implemented, not registered in the set.
 *
 * Testo custom assertion records have no faithful PHPUnit/Pest equivalent.
 */
final class CustomAssertionRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'STUB: Testo custom assertions have no faithful PHPUnit equivalent (not implemented)',
            [],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [];
    }

    #[\Override]
    public function refactor(Node $node): ?Node
    {
        return null;
    }
}
