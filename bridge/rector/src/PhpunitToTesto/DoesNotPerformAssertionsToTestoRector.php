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
 * Rewrites the PHPUnit `#[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]`
 * attribute into Testo's `#[\Testo\Assert\ExpectNoAssertions]`.
 *
 * Both are no-argument, declarative markers for "this test performs no assertions",
 * so the rewrite is a plain attribute-name rename; unrelated attributes are left
 * untouched.
 */
#[TestRectorFixtures('DoesNotPerformAssertionsToTestoRector')]
final class DoesNotPerformAssertionsToTestoRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert PHPUnit #[DoesNotPerformAssertions] attribute into Testo #[\Testo\Assert\ExpectNoAssertions]',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
                        PHP,
                    <<<'PHP'
                        #[\Testo\Assert\ExpectNoAssertions]
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
        if (!$this->isName($node->name, 'PHPUnit\\Framework\\Attributes\\DoesNotPerformAssertions')) {
            return null;
        }

        $node->name = new FullyQualified('Testo\\Assert\\ExpectNoAssertions');

        return $node;
    }
}
