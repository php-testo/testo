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
 * Rewrites the PHPUnit `#[PHPUnit\Framework\Attributes\DataProvider('method')]` attribute into
 * Testo's `#[\Testo\Data\DataProvider('method')]`.
 *
 * Both attributes take the provider method name as a string, so the argument list is preserved
 * verbatim; only the attribute name is rewritten.
 *
 * Legacy `@dataProvider` *docblock* providers are not handled here directly — chain Rector's own
 * `DataProviderAnnotationToAttributeRector` first (it upgrades the annotation to the PHPUnit
 * attribute), then this rule maps that attribute to Testo. `config/sets/phpunit-to-testo.php`
 * wires both, so both the annotation and attribute forms are covered.
 *
 * `#[DataProviderExternal(Class::class, 'method')]` (cross-class providers) is left untouched —
 * see TODO.md.
 */
#[TestRectorFixtures('DataProviderAttributeToTestoRector')]
final class DataProviderAttributeToTestoRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert PHPUnit #[DataProvider] attribute into Testo #[\Testo\Data\DataProvider]',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\DataProvider('provideCases')]
                        PHP,
                    <<<'PHP'
                        #[\Testo\Data\DataProvider('provideCases')]
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
        if (!$this->isName($node->name, 'PHPUnit\\Framework\\Attributes\\DataProvider')) {
            return null;
        }

        $node->name = new FullyQualified('Testo\\Data\\DataProvider');

        return $node;
    }
}
