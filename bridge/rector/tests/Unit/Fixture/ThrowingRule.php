<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Unit\Fixture;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * A rule that crashes on every class, so {@see RectorRunnerTest} can check the crash reaches the
 * test instead of being rolled back silently.
 */
final class ThrowingRule extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Crashes on every class', []);
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    #[\Override]
    public function refactor(Node $node): ?Node
    {
        throw new \LogicException('rule crashed');
    }
}
