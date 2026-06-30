<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites the bare `\Testo\Expect::exception($class)` static call into a PHPUnit
 * `$this->expectException($class)` method call.
 *
 * Testo declares exception expectations up front via the `Expect` facade; PHPUnit
 * uses `$this->expectException()`. The single argument (a class-string or specimen
 * expression) is forwarded unchanged.
 *
 * @todo The fluent modifiers on the returned `ExpectedException` are NOT handled:
 *       `Expect::exception($c)->withMessage($m)`,
 *       `->withMessageContaining($s)`, `->withMessagePattern($re)`, `->withCode($n)`.
 *       Each maps to a SEPARATE PHPUnit statement
 *       (`$this->expectExceptionMessage($m)`, `expectExceptionMessageMatches($re)`,
 *       `expectExceptionCode($n)`), so a faithful conversion must expand one chained
 *       expression statement into several statements. That requires matching the
 *       wrapping `Stmt\Expression` and returning a `Node[]`, which the one-node
 *       `refactor(): ?Node` convention used here cannot express cleanly. Tracked in
 *       TODO.md; for now only the head `Expect::exception()` call is converted and
 *       any chained modifiers are left attached to the rewritten head call (still
 *       fluent but calling PHPUnit methods that do not exist), so chains are flagged
 *       for manual review rather than silently mistranslated.
 */
#[TestRectorFixtures('ExpectExceptionToPhpUnitRector')]
final class ExpectExceptionToPhpUnitRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert `\Testo\Expect::exception($class)` into `$this->expectException($class)`',
            [
                new CodeSample(
                    <<<'PHP'
                        \Testo\Expect::exception(\RuntimeException::class);
                        PHP,
                    <<<'PHP'
                        $this->expectException(\RuntimeException::class);
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [StaticCall::class];
    }

    /**
     * @param StaticCall $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if (!$this->isName($node->class, 'Testo\\Expect')) {
            return null;
        }

        if (!$this->isName($node->name, 'exception')) {
            return null;
        }

        return new MethodCall(
            new Variable('this'),
            new Identifier('expectException'),
            $node->args,
        );
    }
}
