<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Trait_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Internal\PhpunitTestCaseClass;
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
 *
 * Only methods of a PHPUnit test class (one extending `TestCase`, directly or through a base) and of
 * traits are touched: `setUp()` is a common name outside PHPUnit too, e.g. a phpbench
 * `@BeforeMethods` hook, and marking it there would change nothing but mislead.
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

    public function __construct(
        private readonly PhpunitTestCaseClass $testCaseClass,
    ) {}

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
        return [Class_::class, Trait_::class];
    }

    /**
     * @param Class_|Trait_ $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Class_ && !$this->testCaseClass->isTestCase($node)) {
            return null;
        }

        $changed = false;
        foreach ($node->getMethods() as $method) {
            $this->addLifecycleAttribute($method) and $changed = true;
        }

        return $changed ? $node : null;
    }

    /**
     * @return bool Whether the attribute was added.
     */
    private function addLifecycleAttribute(ClassMethod $method): bool
    {
        $name = $this->getName($method->name);
        if ($name === null || !isset(self::MAP[$name])) {
            return false;
        }

        $attributeFqcn = self::MAP[$name];

        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isName($attr->name, $attributeFqcn)) {
                    return false;
                }
            }
        }

        $method->attrGroups[] = new AttributeGroup([
            new Attribute(new FullyQualified($attributeFqcn)),
        ]);

        return true;
    }
}
