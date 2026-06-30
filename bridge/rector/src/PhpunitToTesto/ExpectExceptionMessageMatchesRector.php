<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * STUB — not implemented, not registered.
 *
 * Intended target: PHPUnit `$this->expectExceptionMessageMatches($regex)`.
 *
 * @todo Unconvertible without a regex-matching message expectation in Testo.
 *   PHPUnit's `expectExceptionMessageMatches()` asserts the exception message
 *   matches a PCRE pattern. Testo's `\Testo\Expect::exception(...)->withMessage($s)`
 *   performs (substring/equality) matching on a literal string, not a regex; there
 *   is no `withMessageMatches()`-style API to target. A naive conversion to
 *   `withMessage()` would change the assertion semantics (literal vs pattern), so
 *   the call is left in place and flagged for manual migration.
 */
final class ExpectExceptionMessageMatchesRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'STUB: PHPUnit expectExceptionMessageMatches() (regex) has no Testo equivalent (see @todo)',
            [
                new CodeSample(
                    <<<'PHP'
                        $this->expectExceptionMessageMatches('/boom/');
                        PHP,
                    <<<'PHP'
                        $this->expectExceptionMessageMatches('/boom/');
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Node\Expr\MethodCall::class];
    }

    /**
     * @param Node\Expr\MethodCall $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        // Not implemented — see class-level @todo.
        return null;
    }
}
