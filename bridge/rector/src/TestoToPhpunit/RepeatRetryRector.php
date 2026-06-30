<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * STUB — not implemented, not registered in the set.
 *
 * Intent: convert Testo's `#[Testo\Repeat\Repeat]` / `#[Testo\Retry\Retry]`
 * (RetryPolicy) attributes into a PHPUnit equivalent.
 *
 * @todo No faithful PHPUnit equivalent exists in core PHPUnit. Testo can repeat a
 *   test a fixed number of times and retry flaky tests according to a policy;
 *   PHPUnit core has no `#[Repeat]`/`#[Retry]` attribute and no built-in retry
 *   loop (such behaviour only exists via third-party extensions with diverging
 *   semantics). Mapping is therefore lossy and is left for manual conversion.
 */
final class RepeatRetryRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'STUB: Testo #[Repeat]/#[Retry] attributes have no faithful PHPUnit-core equivalent (not implemented)',
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
