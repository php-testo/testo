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
 * Intended target: PHPUnit `$this->markTestIncomplete($message)`.
 *
 * @todo Unconvertible: Testo has no "incomplete" status. PHPUnit distinguishes
 *   Skipped from Incomplete (a test that is known to be unfinished); Testo only
 *   models Skipped (via a thrown `\Testo\Core\Exception\SkipTest`). Mapping
 *   "incomplete" onto "skipped" would lose the distinction and silently change the
 *   reported outcome, so it is left for the author to decide (skip it, finish it,
 *   or remove it). Contrast with `markTestSkipped`, which IS converted by
 *   {@see MarkTestSkippedToTestoRector}.
 */
final class MarkTestIncompleteRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'STUB: PHPUnit markTestIncomplete() has no Testo "incomplete" status (see @todo)',
            [
                new CodeSample(
                    <<<'PHP'
                        $this->markTestIncomplete('todo');
                        PHP,
                    <<<'PHP'
                        $this->markTestIncomplete('todo');
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Node\Stmt\Expression::class];
    }

    /**
     * @param Node\Stmt\Expression $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        // Not implemented — see class-level @todo.
        return null;
    }
}
