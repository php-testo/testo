<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Adds the matching Testo lifecycle attribute to PHPUnit lifecycle methods,
 * identified by their conventional names:
 *   - setUp                 => #[\Testo\Lifecycle\BeforeTest]
 *   - tearDown              => #[\Testo\Lifecycle\AfterTest]
 *   - setUpBeforeClass      => #[\Testo\Lifecycle\BeforeClass]
 *   - tearDownAfterClass    => #[\Testo\Lifecycle\AfterClass]
 *
 * The method body and signature are kept as-is; only the attribute is added when
 * absent (the rule is idempotent — it skips a method that already carries the
 * target attribute). Unrelated methods are left untouched.
 */
#[TestRectorFixtures('LifecycleMethodToTestoRector')]
final class LifecycleMethodToTestoRector extends AbstractRector
{
    /**
     * PHPUnit lifecycle method name => Testo lifecycle attribute FQCN.
     *
     * @var array<non-empty-string, non-empty-string>
     */
    private const MAP = [
        'setUp' => 'Testo\\Lifecycle\\BeforeTest',
        'tearDown' => 'Testo\\Lifecycle\\AfterTest',
        'setUpBeforeClass' => 'Testo\\Lifecycle\\BeforeClass',
        'tearDownAfterClass' => 'Testo\\Lifecycle\\AfterClass',
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add Testo lifecycle attributes to PHPUnit setUp/tearDown/setUpBeforeClass/tearDownAfterClass methods',
            [
                new CodeSample(
                    <<<'PHP'
                        protected function setUp(): void
                        {
                        }
                        PHP,
                    <<<'PHP'
                        #[\Testo\Lifecycle\BeforeTest]
                        protected function setUp(): void
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
        $method = $this->getName($node->name);
        if ($method === null || !isset(self::MAP[$method])) {
            return null;
        }

        $attributeFqcn = self::MAP[$method];

        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isName($attr->name, $attributeFqcn)) {
                    return null;
                }
            }
        }

        $node->attrGroups[] = new AttributeGroup([
            new Attribute(new FullyQualified($attributeFqcn)),
        ]);

        return $node;
    }
}
