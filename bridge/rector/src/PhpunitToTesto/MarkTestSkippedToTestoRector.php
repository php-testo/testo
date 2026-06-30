<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Name\FullyQualified;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites PHPUnit `$this->markTestSkipped($message)` (also `self::`/`static::`)
 * into a `throw new \Testo\Core\Exception\SkipTest($message)` statement.
 *
 * Testo has no "skip" method; the runner maps any thrown `SkipTest` (and its
 * subclasses) to the Skipped status. The message argument is preserved as the
 * exception message.
 *
 * `markTestIncomplete` is intentionally NOT converted: Testo has no "incomplete"
 * status. See TODO.md / MarkTestIncompleteRector.
 */
#[TestRectorFixtures('MarkTestSkippedToTestoRector')]
final class MarkTestSkippedToTestoRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert PHPUnit markTestSkipped() into throw new \Testo\Core\Exception\SkipTest()',
            [
                new CodeSample(
                    <<<'PHP'
                        $this->markTestSkipped('not ready');
                        PHP,
                    <<<'PHP'
                        throw new \Testo\Core\Exception\SkipTest('not ready');
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [MethodCall::class, StaticCall::class];
    }

    /**
     * @param MethodCall|StaticCall $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof MethodCall) {
            if (!$this->isName($node->var, 'this')) {
                return null;
            }
        } elseif (!$this->isName($node->class, 'self') && !$this->isName($node->class, 'static')) {
            return null;
        }

        if (!$this->isName($node->name, 'markTestSkipped')) {
            return null;
        }

        # Replace the call expression with a `throw new SkipTest(...)` expression; the enclosing
        # statement keeps wrapping it, yielding `throw new \Testo\Core\Exception\SkipTest(...);`.
        return new Throw_(
            new New_(new FullyQualified('Testo\\Core\\Exception\\SkipTest'), $node->args),
        );
    }
}
