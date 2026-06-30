<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Function_;
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
 * Collapses PHPUnit group declarations on a class/method/function — both the `@group` docblock
 * annotation and the (repeatable, single-name) `#[PHPUnit\Framework\Attributes\Group]` attribute —
 * into a single variadic `#[\Testo\Filter\Group('a', 'b', …)]`.
 *
 * The arity differs: PHPUnit repeats the attribute (one name each), whereas Testo's `Group` is
 * variadic and NOT repeatable — so every PHPUnit group source on a node is merged into one Testo
 * attribute. Names keep their first-seen order and are de-duplicated.
 *
 * Like the data-provider rules, the docblock mechanics need no `phpunit/phpunit` install.
 *
 * Scope: per-node only. Testo derives a test's effective group set as the union over its method,
 * class, parents and traits at run time, so converting each node's own groups verbatim is
 * faithful — no cross-hierarchy flattening is attempted here.
 */
#[TestRectorFixtures('GroupToTestoRector')]
final class GroupToTestoRector extends AbstractRector
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
            'Collapse PHPUnit @group annotations and #[Group] attributes into one variadic #[\Testo\Filter\Group]',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\Group('db')]
                        #[\PHPUnit\Framework\Attributes\Group('slow')]
                        public function testX(): void
                        {
                        }
                        PHP,
                    <<<'PHP'
                        #[\Testo\Filter\Group('db', 'slow')]
                        public function testX(): void
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
        return [Class_::class, ClassMethod::class, Function_::class];
    }

    /**
     * @param Class_|ClassMethod|Function_ $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        $phpDocInfo = $this->phpDocInfoFactory->createFromNode($node);
        $groupTags = $phpDocInfo instanceof PhpDocInfo ? $phpDocInfo->getTagsByName('group') : [];

        # Read-only pass: gather names from PHPUnit Group attributes and @group tags.
        $names = [];
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if (!$this->isName($attr->name, 'PHPUnit\\Framework\\Attributes\\Group')) {
                    continue;
                }
                $arg = $attr->args[0] ?? null;
                if ($arg !== null && $arg->value instanceof String_) {
                    $this->collect($names, $arg->value->value);
                }
            }
        }
        foreach ($groupTags as $tag) {
            if ($tag->value instanceof GenericTagValueNode) {
                $token = \strtok($tag->value->value === '' ? ' ' : $tag->value->value, " \t\n\r\0\x0B");
                $token === false or $this->collect($names, $token);
            }
        }

        if ($names === []) {
            return null;
        }

        # Mutate: drop PHPUnit Group attributes (and any group left empty), drop @group tags,
        # then append the single Testo Group attribute.
        $keptGroups = [];
        foreach ($node->attrGroups as $attrGroup) {
            $keptAttrs = [];
            foreach ($attrGroup->attrs as $attr) {
                $this->isName($attr->name, 'PHPUnit\\Framework\\Attributes\\Group') or $keptAttrs[] = $attr;
            }
            $keptAttrs === [] or $keptGroups[] = new AttributeGroup($keptAttrs);
        }

        foreach ($groupTags as $tag) {
            $tag->value instanceof GenericTagValueNode and $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $tag);
        }

        $keptGroups[] = $this->phpAttributeGroupFactory->createFromClassWithItems('Testo\\Filter\\Group', $names);
        $node->attrGroups = $keptGroups;

        $phpDocInfo instanceof PhpDocInfo and $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($node);

        return $node;
    }

    /**
     * @param list<string> $names
     */
    private function collect(array &$names, string $name): void
    {
        $name = \trim($name, '()');
        $name === '' || \in_array($name, $names, true) or $names[] = $name;
    }
}
