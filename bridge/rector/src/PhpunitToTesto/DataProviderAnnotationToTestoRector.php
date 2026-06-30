<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\PhpAttribute\NodeFactory\PhpAttributeGroupFactory;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Converts a legacy `@dataProvider <method>` docblock annotation into a Testo
 * `#[\Testo\Data\DataProvider('<method>')]` attribute, removing the tag from the docblock.
 *
 * This is the annotation-form counterpart of {@see DataProviderAttributeToTestoRector}; together
 * they cover both source shapes (docblock and PHPUnit attribute). The docblock mechanics mirror
 * Rector's own `DataProviderAnnotationToAttributeRector`, but the conversion targets Testo's
 * attribute directly and is deliberately NOT gated on PHPUnit `TestCase` detection — so it works
 * even when `phpunit/phpunit` is not installed (Testo suites need not depend on it).
 *
 * Cross-class providers (`@dataProvider Other::method`) are left in place — Testo's `DataProvider`
 * takes a single provider, and the external form is rare in the annotation shape. See TODO.md.
 */
#[TestRectorFixtures('DataProviderAnnotationToTestoRector')]
final class DataProviderAnnotationToTestoRector extends AbstractRector
{
    public function __construct(
        private readonly PhpDocInfoFactory $phpDocInfoFactory,
        private readonly PhpDocTagRemover $phpDocTagRemover,
        private readonly PhpAttributeGroupFactory $phpAttributeGroupFactory,
        private readonly DocBlockUpdater $docBlockUpdater,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert a @dataProvider annotation into a Testo #[\Testo\Data\DataProvider] attribute',
            [
                new CodeSample(
                    <<<'PHP'
                        /**
                         * @dataProvider provideCases
                         */
                        public function testX(int $a): void
                        {
                        }
                        PHP,
                    <<<'PHP'
                        #[\Testo\Data\DataProvider('provideCases')]
                        public function testX(int $a): void
                        {
                        }
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
        $phpDocInfo = $this->phpDocInfoFactory->createFromNode($node);
        if (!$phpDocInfo instanceof PhpDocInfo) {
            return null;
        }

        $tags = $phpDocInfo->getTagsByName('dataProvider');
        if ($tags === []) {
            return null;
        }

        $changed = false;
        foreach ($tags as $tag) {
            if (!$tag->value instanceof GenericTagValueNode) {
                continue;
            }

            $name = \strtok($tag->value->value === '' ? ' ' : $tag->value->value, " \t\n\r\0\x0B");
            $name = $name === false ? '' : \trim($name, '()');
            if ($name === '' || \str_contains($name, '::')) {
                # Empty or cross-class (`Other::method`) provider — leave for manual handling.
                continue;
            }

            $attributeGroup = $this->phpAttributeGroupFactory->createFromClassWithItems('Testo\\Data\\DataProvider', [$name]);
            if (!$this->alreadyPresent($node, $attributeGroup)) {
                $node->attrGroups[] = $attributeGroup;
            }

            $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $tag);
            $changed = true;
        }

        if (!$changed) {
            return null;
        }

        $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($node);

        return $node;
    }

    private function alreadyPresent(ClassMethod $classMethod, AttributeGroup $attributeGroup): bool
    {
        foreach ($classMethod->attrGroups as $existing) {
            if ($this->nodeComparator->areNodesEqual($existing, $attributeGroup)) {
                return true;
            }
        }

        return false;
    }
}
