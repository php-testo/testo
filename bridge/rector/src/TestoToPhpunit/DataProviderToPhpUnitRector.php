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
 * Rewrites Testo's data attributes into their PHPUnit equivalents — the mirror of
 * `DataProviderAttributeToTestoRector`:
 *
 *  - `#[\Testo\Data\DataProvider('method')]` → `#[\PHPUnit\Framework\Attributes\DataProvider('method')]`
 *  - `#[\Testo\Data\DataSet([...], 'label')]` → `#[\PHPUnit\Framework\Attributes\TestWith([...], 'label')]`
 *
 * Both pairs share their argument shape: `DataProvider` carries the provider method name as a
 * string, and `DataSet`/`TestWith` both take `(array $data, ?string $name = null)`. Only the
 * attribute class differs, so the argument list (the data array and the optional label) is
 * preserved verbatim and the replacement name is emitted fully-qualified — no import management.
 *
 * Both Testo attributes are repeatable, as are both PHPUnit targets, so a method carrying several
 * `#[DataSet]` attributes is rewritten into the same number of `#[TestWith]` attributes in order.
 */
#[TestRectorFixtures('DataProviderToPhpUnitRector')]
final class DataProviderToPhpUnitRector extends AbstractRector
{
    private const RENAMES = [
        'Testo\\Data\\DataProvider' => 'PHPUnit\\Framework\\Attributes\\DataProvider',
        'Testo\\Data\\DataSet' => 'PHPUnit\\Framework\\Attributes\\TestWith',
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert Testo #[\Testo\Data\DataProvider]/#[\Testo\Data\DataSet] into PHPUnit #[DataProvider]/#[TestWith]',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\Testo\Data\DataProvider('provideCases')]
                        #[\Testo\Data\DataSet([1, 2], 'pair')]
                        PHP,
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\DataProvider('provideCases')]
                        #[\PHPUnit\Framework\Attributes\TestWith([1, 2], 'pair')]
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
        foreach (self::RENAMES as $from => $to) {
            if (!$this->isName($node->name, $from)) {
                continue;
            }

            $node->name = new FullyQualified($to);

            return $node;
        }

        return null;
    }
}
