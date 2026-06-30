<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * STUB — not implemented, not registered in the set.
 *
 * Intent: convert Testo's memory-leak expectations (`\Testo\Expect::notLeaks(...)`
 * / `\Testo\Expect::leaks(...)`) into a PHPUnit equivalent.
 *
 * @todo No faithful PHPUnit equivalent exists. Testo can assert that specific
 *   objects are (or are not) still cached in memory after a test completes — a
 *   capability backed by Testo's pipeline and garbage-collection probing. PHPUnit
 *   has no built-in memory-leak assertion and no hook that observes object liveness
 *   after the test body returns. Any conversion would have to drop the expectation
 *   or replace it with a bespoke, framework-specific shim, so it is intentionally
 *   left unconverted for manual handling.
 */
final class MemoryLeakExpectationRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'STUB: Testo memory-leak expectations (Expect::notLeaks/leaks) have no faithful PHPUnit equivalent (not implemented)',
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
