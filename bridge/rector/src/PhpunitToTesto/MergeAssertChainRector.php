<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use Rector\Contract\PhpParser\Node\StmtsAwareInterface;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PhpParser\Enum\NodeGroup;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Merges adjacent `\Testo\Assert::<type>($var)->…` fluent chains that share an identical typed head
 * into one chain, concatenating their matcher tails:
 *
 *     \Testo\Assert::array($list)->hasKeys('a');
 *     \Testo\Assert::array($list)->isList();
 *     // becomes
 *     \Testo\Assert::array($list)->hasKeys('a')->isList();
 *
 * Because it reasons across sibling statements (which chains are adjacent, share a head, and have
 * nothing between them), it operates at the statements level: it matches the enclosing
 * {@see StmtsAwareInterface} node and rewrites its `->stmts`.
 *
 * Two statements merge only when their heads are byte-for-byte the same assertion of the same
 * subject — the same `Assert::<type>` method applied to the same single **variable** (`$list`):
 *   - The subject must be a plain variable, never a call/property fetch. Re-reading a variable has
 *     no side effect and its value is stable between two adjacent statements, so eliding the second
 *     head's redundant type check is behaviour-preserving. A `Assert::array($this->all())->…` head
 *     is left alone (merging would drop one evaluation of `$this->all()`).
 *   - The head must take exactly that one argument, so comparison/needle assertions like
 *     `Assert::count($v, 2)` or `Assert::instanceOf($v, X)` (two args, and not a fluent head anyway)
 *     are never touched.
 *   - Different variable, different type head, or any intervening statement ends the run.
 *   - Both chains must open with the same set of string comparison modifiers (`ignoringCase()`,
 *     `ignoringWhitespace(...)`, …), in any order and with the same arguments as written: a modifier
 *     applies to every check after it and has no off switch, so a strict check appended to a modified
 *     chain would turn lenient. Equal sets keep every check in its own mode. A chain with a modifier
 *     after a check never merges.
 *
 * Faithful: the merged chain asserts the type once instead of once per statement, but the subject is
 * the same unchanged variable, so the elided checks were redundant (had the type been wrong, the
 * first head would have thrown and the later statements never run). Only the count of recorded
 * type-assertion successes drops; the test's pass/fail outcome is identical.
 *
 * Only merges inside a class, for consistency with the other imperative body rules — a chain in a
 * free function or at namespace level is left untouched.
 *
 * Note: this tidies hand-written Testo pipes; it does NOT fold the flat facade calls
 * ({@see AssertCallToTestoRector}) emitted when converting PHPUnit `assert*`, because those
 * (`same`/`true`/`count`/…) are void or would need a typed head that changes failure semantics.
 */
#[TestRectorFixtures('MergeAssertChainRector')]
final class MergeAssertChainRector extends AbstractRector
{
    /**
     * Chain methods that switch the comparison mode of the checks after them instead of asserting.
     *
     * @var list<non-empty-string>
     */
    private const MODIFIERS = ['ignoringCase', 'ignoringLineEndings', 'ignoringWhitespace', 'ignoringBlankLines', 'ignoringAnsi'];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Merge adjacent `\Testo\Assert::<type>($var)` fluent chains on the same variable into one chain',
            [
                new CodeSample(
                    <<<'PHP'
                        \Testo\Assert::array($list)->hasKeys('a');
                        \Testo\Assert::array($list)->isList();
                        PHP,
                    <<<'PHP'
                        \Testo\Assert::array($list)->hasKeys('a')->isList();
                        PHP,
                ),
            ],
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    #[\Override]
    public function getNodeTypes(): array
    {
        return NodeGroup::STMTS_AWARE;
    }

    /**
     * @param StmtsAwareInterface $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        $stmts = $node->stmts;
        if ($stmts === null) {
            return null;
        }

        # Only merge inside a class, matching the other imperative body rules. A chain in a free
        # function or at namespace level is left untouched.
        if (!$this->isInClassScope($node)) {
            return null;
        }

        $changed = false;
        $result = [];
        $count = \count($stmts);

        for ($i = 0; $i < $count; ++$i) {
            $stmt = $stmts[$i];
            $head = $stmt instanceof Expression ? $this->parseChain($stmt) : null;

            if ($head === null) {
                $result[] = $stmt;
                continue;
            }

            # Collect the matcher tail of this statement, then absorb the uninterrupted run of
            # following chains that share the identical head (same type + same variable) and the
            # same comparison modifiers. The absorbed chains drop their modifiers: the ones already
            # in this chain apply to every check appended after them.
            $links = $head['links'];
            $absorbed = 0;
            while ($i + 1 < $count) {
                $next = $stmts[$i + 1];
                $nextHead = $next instanceof Expression ? $this->parseChain($next) : null;
                if ($nextHead === null || $nextHead['type'] !== $head['type'] || $nextHead['subject'] !== $head['subject']
                    || $nextHead['modifiers'] !== $head['modifiers']) {
                    break;
                }

                $links = [...$links, ...$nextHead['checks']];
                ++$absorbed;
                ++$i;
            }

            if ($absorbed === 0) {
                # Nothing absorbed — leave the statement exactly as it was.
                $result[] = $stmt;
                continue;
            }

            $chain = $head['call'];
            foreach ($links as $link) {
                $chain = new MethodCall($chain, $link->name, $link->args);
            }

            \assert($stmt instanceof Expression);
            $stmt->expr = $chain;
            $result[] = $stmt;
            $changed = true;
        }

        if (!$changed) {
            return null;
        }

        $node->stmts = $result;

        return $node;
    }

    /**
     * Parses a statement into a mergeable `\Testo\Assert::<type>($var)->matcher()…` chain, or null
     * when it is not one.
     *
     * @return array{type: non-empty-string, subject: non-empty-string, modifiers: array<non-empty-string, string>, call: StaticCall, links: list<MethodCall>, checks: list<MethodCall>}|null
     */
    private function parseChain(Expression $stmt): ?array
    {
        $expr = $stmt->expr;
        if (!$expr instanceof MethodCall) {
            return null;
        }

        # Walk the chain outermost-first down to the head `Assert::<type>(...)`, collecting matchers.
        $links = [];
        $cursor = $expr;
        while ($cursor instanceof MethodCall) {
            $links[] = $cursor;
            $cursor = $cursor->var;
        }

        if (!$cursor instanceof StaticCall || !$this->isName($cursor->class, 'Testo\\Assert')) {
            return null;
        }

        # The head must be `Assert::<type>($var)`: exactly one argument, a plain variable. This admits
        # the fluent type heads and excludes multi-arg comparison/needle assertions.
        if (\count($cursor->args) !== 1) {
            return null;
        }
        $arg = $cursor->args[0];
        if (!$arg instanceof Arg || !$arg->value instanceof Variable) {
            return null;
        }

        $type = $this->getName($cursor->name);
        $subject = $this->getName($arg->value);
        if ($type === null || $subject === null) {
            return null;
        }

        $links = \array_reverse($links); # head-first matcher order

        # Split the leading comparison modifiers from the checks. A modifier after a check changes
        # the mode mid-chain, which no merge can keep, so such a chain is not mergeable. A repeated
        # modifier keeps the arguments of its last call, the one that takes effect.
        $modifiers = [];
        $checks = [];
        foreach ($links as $link) {
            $name = $this->getName($link->name);
            if ($name !== null && \in_array($name, self::MODIFIERS, true)) {
                if ($checks !== []) {
                    return null;
                }
                $modifiers[$name] = $this->nodeComparator->printWithoutComments($link->args);
                continue;
            }
            $checks[] = $link;
        }
        \ksort($modifiers);

        return [
            'type' => $type,
            'subject' => $subject,
            'modifiers' => $modifiers,
            'call' => $cursor,
            'links' => $links,
            'checks' => $checks,
        ];
    }

    /**
     * Whether the statements node sits inside a class. Chains are only merged there, matching the
     * other imperative body rules; a run in a free function or at namespace level is left untouched.
     */
    private function isInClassScope(Node $node): bool
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);

        return $scope instanceof Scope && $scope->isInClass();
    }
}
