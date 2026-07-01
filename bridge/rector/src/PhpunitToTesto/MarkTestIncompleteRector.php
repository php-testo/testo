<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites PHPUnit `$this->markTestIncomplete($message)` (also `self::`/`static::`)
 * into a `throw new \Testo\Core\Exception\SkipTest('Incomplete: ' . $message)` statement.
 *
 * Testo has no dedicated "incomplete" status — the runner only models Skipped (via a
 * thrown `\Testo\Core\Exception\SkipTest`). Incomplete is the *nearest* status: like
 * Skipped it neither passes nor fails and halts the test at the call site, so by default
 * behaviour they coincide.
 *
 * This is therefore a deliberately LOSSY mapping. The one thing PHPUnit distinguishes —
 * "this test is unfinished" versus "this test is not applicable here" — would vanish if we
 * folded Incomplete silently into a plain skip. To keep the nuance visible (and
 * re-detectable by a future reverse rule), the original reason is prefixed with
 * `Incomplete: `. Contrast with {@see MarkTestSkippedToTestoRector}, which is a faithful
 * 1:1 conversion of `markTestSkipped`.
 */
#[TestRectorFixtures('MarkTestIncompleteRector')]
final class MarkTestIncompleteRector extends AbstractRector
{
    private const REASON_PREFIX = 'Incomplete: ';

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert PHPUnit markTestIncomplete() into throw new \Testo\Core\Exception\SkipTest() with an "Incomplete:" reason prefix',
            [
                new CodeSample(
                    <<<'PHP'
                        $this->markTestIncomplete('todo');
                        PHP,
                    <<<'PHP'
                        throw new \Testo\Core\Exception\SkipTest('Incomplete: todo');
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

        if (!$this->isName($node->name, 'markTestIncomplete')) {
            return null;
        }

        # Only convert inside a class: the skip belongs to a test method. A stray call in a free
        # function or at namespace level is left untouched.
        if (!$this->isInClassScope($node)) {
            return null;
        }

        # Replace the call expression with a `throw new SkipTest(...)` expression; the enclosing
        # statement keeps wrapping it, yielding `throw new \Testo\Core\Exception\SkipTest(...);`.
        return new Throw_(
            new New_(
                new FullyQualified('Testo\\Core\\Exception\\SkipTest'),
                [new Arg($this->buildReason($node->args))],
            ),
        );
    }

    /**
     * @param array<Node\Arg|Node\VariadicPlaceholder> $args
     */
    private function buildReason(array $args): Node\Expr
    {
        $first = $args[0] ?? null;

        # markTestIncomplete() with no message — keep just the marker.
        if (!$first instanceof Arg) {
            return new String_(\rtrim(self::REASON_PREFIX, ': '));
        }

        $message = $first->value;

        # A literal message folds into one readable string; anything else (variable,
        # concat, call) is prefixed via runtime concatenation so the original expression
        # is still evaluated exactly once.
        return $message instanceof String_
            ? new String_(self::REASON_PREFIX . $message->value)
            : new Concat(new String_(self::REASON_PREFIX), $message);
    }

    /**
     * Whether $node sits inside a class. The skip is only converted there; a call in a free function
     * or at namespace level is left untouched.
     */
    private function isInClassScope(Node $node): bool
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);

        return $scope instanceof Scope && $scope->isInClass();
    }
}
