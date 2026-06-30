<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Name\FullyQualified;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites the PHPUnit `#[PHPUnit\Framework\Attributes\CoversClass(X::class)]`
 * attribute into Testo's `#[\Testo\Codecov\Covers(X::class)]`.
 *
 * Both attributes take the covered class as a `::class` constant, so the argument
 * list is preserved verbatim; only the attribute name is rewritten. Unrelated
 * attributes are left untouched.
 */
#[TestRectorFixtures('CoversClassToCoversRector')]
final class CoversClassToCoversRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert PHPUnit #[CoversClass] attribute into Testo #[\Testo\Codecov\Covers]',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\CoversClass(Service::class)]
                        PHP,
                    <<<'PHP'
                        #[\Testo\Codecov\Covers(Service::class)]
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Attribute::class];
    }

    /**
     * @param Attribute $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if (!$this->isName($node->name, 'PHPUnit\\Framework\\Attributes\\CoversClass')) {
            return null;
        }

        $node->name = new FullyQualified('Testo\\Codecov\\Covers');

        return $node;
    }
}
