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
 * Rewrites the PHPUnit `#[PHPUnit\Framework\Attributes\DataProvider('method')]` attribute into
 * Testo's `#[\Testo\Data\DataProvider('method')]`.
 *
 * Both attributes take the provider method name first, so it moves over positionally; a named
 * `methodName:` loses its name, which Testo spells differently. PHPUnit's `validateArgumentCount`
 * and `skipWhenEmpty` have no Testo option and are dropped. An unknown name or an unpacked argument
 * leaves the attribute untouched.
 *
 * Legacy `@dataProvider` *docblock* providers are not handled here directly — chain Rector's own
 * `DataProviderAnnotationToAttributeRector` first (it upgrades the annotation to the PHPUnit
 * attribute), then this rule maps that attribute to Testo. `config/phpunit-to-testo.php`
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

        $args = PositionalArgs::byPosition($node->args, ['methodName', 'validateArgumentCount', 'skipWhenEmpty']);
        $methodName = $args[0] ?? null;
        if ($methodName === null || \array_key_last($args) > 2) {
            return null;
        }

        $node->name = new FullyQualified('Testo\\Data\\DataProvider');
        $node->args = [$methodName];

        return $node;
    }
}
