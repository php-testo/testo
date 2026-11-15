<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * STUB — not implemented, not registered in the set.
 *
 * Intent: convert `#[\Testo\ErrorHandler\ExpectErrorHandlerChange]` into a PHPUnit equivalent.
 *
 * @todo No faithful PHPUnit equivalent exists. The attribute declares that a test installs an
 *   error handler and leaves it in place, so the error-handler plugin keeps the test out of
 *   `Risky`. PHPUnit performs the same stack check unconditionally and has no attribute that
 *   waives it: a test leaving a handler behind is always "did not remove its own error handlers".
 *   `#[WithoutErrorHandler]` is not that switch — it only stops PHPUnit from installing its own
 *   handler for the test. Dropping the Testo attribute would silently turn a declared, passing
 *   test into a risky one, so it is left unconverted for manual handling.
 */
final class ExpectErrorHandlerChangeRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'STUB: Testo #[ExpectErrorHandlerChange] (declared error-handler change) has no faithful PHPUnit equivalent (not implemented)',
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
