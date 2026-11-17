<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Name\FullyQualified;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Internal\PositionalArgs;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites the PHPUnit `#[PHPUnit\Framework\Attributes\CoversClass(X::class)]`
 * attribute into Testo's `#[\Testo\Codecov\Covers(X::class)]`.
 *
 * Both attributes take the covered class first, so it moves over positionally; a named
 * `className:` loses its name, which Testo spells differently. An unknown name, an extra or an
 * unpacked argument leaves the attribute untouched, as do unrelated attributes.
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

        $args = PositionalArgs::bind($node->args, ['className']);
        if ($args === null || \count($args) !== 1) {
            return null;
        }

        $node->name = new FullyQualified('Testo\\Codecov\\Covers');
        $node->args = $args;

        return $node;
    }
}
