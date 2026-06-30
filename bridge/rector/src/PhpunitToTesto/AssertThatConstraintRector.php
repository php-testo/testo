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
 * Intended target: PHPUnit `assertThat($value, $constraint[, $message])`.
 *
 * @todo Unconvertible without modelling PHPUnit constraint objects. `assertThat`
 *   accepts a `PHPUnit\Framework\Constraint\Constraint` instance (often built via
 *   factory methods like `$this->equalTo()`, `$this->isInstanceOf()`,
 *   `$this->logicalAnd(...)`, `$this->callback(...)`). Testo's `Assert` facade has
 *   no constraint-object abstraction; each constraint would need bespoke mapping to
 *   a concrete `Assert::*` call, and composite constraints (logicalAnd/Or/Not) and
 *   `callback()` have no faithful flat equivalent. Left unconverted so the test
 *   stays visibly unmigrated rather than silently mistranslated.
 */
final class AssertThatConstraintRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'STUB: PHPUnit assertThat() + constraint objects have no Testo equivalent (see @todo)',
            [
                new CodeSample(
                    <<<'PHP'
                        $this->assertThat($value, $this->isInstanceOf(Foo::class));
                        PHP,
                    <<<'PHP'
                        $this->assertThat($value, $this->isInstanceOf(Foo::class));
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
