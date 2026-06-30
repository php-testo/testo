<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites Testo's `#[Testo\Codecov\Covers(X::class)]` class attribute into
 * PHPUnit's `#[\PHPUnit\Framework\Attributes\CoversClass(X::class)]`.
 *
 * Both attributes scope coverage to a class; only the attribute class differs.
 * The arguments are preserved verbatim, so `Covers(Foo::class)` becomes
 * `CoversClass(Foo::class)`. The replacement name is emitted fully-qualified so
 * no import management is required. Unrelated class attributes are left in place.
 */
#[TestRectorFixtures('CoversToCoversClassRector')]
final class CoversToCoversClassRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert class-level `#[Testo\Codecov\Covers(X::class)]` into `#[\PHPUnit\Framework\Attributes\CoversClass(X::class)]`',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\Testo\Codecov\Covers(Foo::class)]
                        class FooTest {}
                        PHP,
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\CoversClass(Foo::class)]
                        class FooTest {}
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        $changed = false;

        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attribute) {
                if (!$this->isName($attribute->name, 'Testo\\Codecov\\Covers')) {
                    continue;
                }

                $attribute->name = new FullyQualified('PHPUnit\\Framework\\Attributes\\CoversClass');
                $changed = true;
            }
        }

        return $changed ? $node : null;
    }
}
