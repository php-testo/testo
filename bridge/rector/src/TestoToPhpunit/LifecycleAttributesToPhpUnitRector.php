<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites Testo lifecycle method attributes into their PHPUnit attribute equivalents:
 *   - `Testo\Lifecycle\BeforeTest`  => `\PHPUnit\Framework\Attributes\Before`
 *   - `Testo\Lifecycle\AfterTest`   => `\PHPUnit\Framework\Attributes\After`
 *   - `Testo\Lifecycle\BeforeClass` => `\PHPUnit\Framework\Attributes\BeforeClass`
 *   - `Testo\Lifecycle\AfterClass`  => `\PHPUnit\Framework\Attributes\AfterClass`
 *
 * Only the attribute class is swapped; any arguments (e.g. Testo's `priority`)
 * are preserved verbatim — PHPUnit ignores unknown arguments so the rewrite stays
 * faithful for the common no-argument case. The replacement names are emitted
 * fully-qualified so no import management is required. Unrelated method attributes
 * are left in place.
 */
#[TestRectorFixtures('LifecycleAttributesToPhpUnitRector')]
final class LifecycleAttributesToPhpUnitRector extends AbstractRector
{
    /**
     * Testo lifecycle attribute FQN => PHPUnit attribute FQN.
     *
     * @var array<non-empty-string, non-empty-string>
     */
    private const MAP = [
        'Testo\\Lifecycle\\BeforeTest' => 'PHPUnit\\Framework\\Attributes\\Before',
        'Testo\\Lifecycle\\AfterTest' => 'PHPUnit\\Framework\\Attributes\\After',
        'Testo\\Lifecycle\\BeforeClass' => 'PHPUnit\\Framework\\Attributes\\BeforeClass',
        'Testo\\Lifecycle\\AfterClass' => 'PHPUnit\\Framework\\Attributes\\AfterClass',
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert Testo\Lifecycle\* method attributes into PHPUnit lifecycle attributes (Before/After/BeforeClass/AfterClass)',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\Testo\Lifecycle\BeforeTest]
                        public function prepare(): void {}
                        PHP,
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\Before]
                        public function prepare(): void {}
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        $changed = false;

        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attribute) {
                foreach (self::MAP as $testo => $phpunit) {
                    if (!$this->isName($attribute->name, $testo)) {
                        continue;
                    }

                    $attribute->name = new FullyQualified($phpunit);
                    $changed = true;
                    break;
                }
            }
        }

        return $changed ? $node : null;
    }
}
