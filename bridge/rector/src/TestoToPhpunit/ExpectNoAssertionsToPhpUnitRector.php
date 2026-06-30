<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Name\FullyQualified;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites Testo's `#[\Testo\Assert\ExpectNoAssertions]` attribute into PHPUnit's
 * `#[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]`.
 *
 * Both are no-argument, declarative markers for "this test performs no assertions",
 * so the rewrite is a plain attribute-name rename; unrelated attributes are left
 * untouched.
 *
 * @todo Testo's `#[ExpectNoAssertions]` may also sit at CLASS level (covering every
 *   test method), but PHPUnit's `#[DoesNotPerformAssertions]` is method-level only.
 *   A class-level marker rewritten in place becomes a class-level PHPUnit attribute
 *   that PHPUnit will not honor; a faithful conversion would fan the marker out onto
 *   each test method. Left as a follow-up.
 */
#[TestRectorFixtures('ExpectNoAssertionsToPhpUnitRector')]
final class ExpectNoAssertionsToPhpUnitRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert Testo #[\Testo\Assert\ExpectNoAssertions] attribute into PHPUnit #[DoesNotPerformAssertions]',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\Testo\Assert\ExpectNoAssertions]
                        PHP,
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
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
        if (!$this->isName($node->name, 'Testo\\Assert\\ExpectNoAssertions')) {
            return null;
        }

        $node->name = new FullyQualified('PHPUnit\\Framework\\Attributes\\DoesNotPerformAssertions');

        return $node;
    }
}
