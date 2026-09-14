<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * STUB — not implemented, not registered in the set.
 *
 * Intent: convert `#[\PHPUnit\Framework\Attributes\WithoutErrorHandler]` into a Testo equivalent.
 *
 * @todo No faithful Testo equivalent exists. The attribute tells PHPUnit not to install its own
 *   error handler for one test. Testo core installs no error handler at all, and the opt-in
 *   `testo/error-handler` plugin applies per suite, with no per-test opt-out. In a project without
 *   the plugin the attribute is a no-op and could be dropped; in a project with it there is nothing
 *   to map to, and `#[\Testo\ErrorHandler\ExpectErrorHandlerChange]` means something else (the test
 *   changes the handler stack on purpose). Left unconverted for manual handling.
 */
final class WithoutErrorHandlerRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'STUB: PHPUnit #[WithoutErrorHandler] (per-test opt-out of the runner error handler) has no faithful Testo equivalent (not implemented)',
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
