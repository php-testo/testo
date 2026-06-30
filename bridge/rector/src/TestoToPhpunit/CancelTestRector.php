<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * STUB — not implemented, not registered in the set.
 *
 * Intent: convert `throw new \Testo\Core\Exception\CancelTest(...)` into a PHPUnit
 * equivalent.
 *
 * @todo No faithful PHPUnit equivalent exists. `CancelTest` represents an EXTERNAL
 *   interruption signal (deadline reached, fail-fast, cooperative shutdown) handed
 *   to the running test by the pipeline — it is not a verdict the test author
 *   chooses, and PHPUnit has no notion of cancellation distinct from skip
 *   (`markTestSkipped`) or incomplete (`markTestIncomplete`). Collapsing it onto
 *   either would misrepresent the semantics, so it is left unconverted for manual
 *   handling.
 */
final class CancelTestRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'STUB: Testo CancelTest (external interruption signal) has no faithful PHPUnit equivalent (not implemented)',
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
