<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Converts Testo's `#[\Testo\ErrorHandler\WithoutErrorHandler]` into PHPUnit's
 * `#[\PHPUnit\Framework\Attributes\WithoutErrorHandler]`.
 *
 * Both keep the runner's own error handler off one test, so a method-level attribute is a plain
 * rename. Testo allows the attribute on a **class** too; PHPUnit's is `TARGET_METHOD` only, so a
 * class-level attribute is fanned out onto each test method — the same method set
 * {@see TestClassToTestCaseRector} uses — and removed from the class. A method already carrying
 * its own copy is left alone.
 *
 * **Residual:** a `#[WithoutErrorHandler]` on a free function is left untouched — PHPUnit has no
 * function-level tests to host it.
 */
#[TestRectorFixtures('WithoutErrorHandlerToPhpUnitRector')]
final class WithoutErrorHandlerToPhpUnitRector extends AbstractRector
{
    private const TESTO = 'Testo\\ErrorHandler\\WithoutErrorHandler';
    private const PHPUNIT = 'PHPUnit\\Framework\\Attributes\\WithoutErrorHandler';

    /** @var list<non-empty-string> */
    private const LIFECYCLE_ATTRIBUTES = [
        'Testo\\Lifecycle\\BeforeTest',
        'Testo\\Lifecycle\\AfterTest',
        'Testo\\Lifecycle\\BeforeClass',
        'Testo\\Lifecycle\\AfterClass',
        'PHPUnit\\Framework\\Attributes\\Before',
        'PHPUnit\\Framework\\Attributes\\After',
        'PHPUnit\\Framework\\Attributes\\BeforeClass',
        'PHPUnit\\Framework\\Attributes\\AfterClass',
    ];

    /** @var list<string> */
    private const LIFECYCLE_NAMES = ['setup', 'teardown', 'setupbeforeclass', 'teardownafterclass'];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert Testo #[\Testo\ErrorHandler\WithoutErrorHandler] into PHPUnit #[WithoutErrorHandler], fanning a class-level attribute out onto each test method',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\Testo\ErrorHandler\WithoutErrorHandler]
                        public function test(): void {}
                        PHP,
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
                        public function test(): void {}
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        $changed = false;
        foreach ($node->getMethods() as $method) {
            foreach ($method->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    if ($this->isName($attr->name, self::TESTO)) {
                        $attr->name = new FullyQualified(self::PHPUNIT);
                        $changed = true;
                    }
                }
            }
        }

        return $this->fanOutClassLevel($node) || $changed ? $node : null;
    }

    /**
     * Push a class-level attribute down onto each test method as its PHPUnit form and drop it from
     * the class. Returns whether anything changed.
     */
    private function fanOutClassLevel(Class_ $class): bool
    {
        if (!$this->hasClassAttribute($class)) {
            return false;
        }

        $targets = $this->testMethods($class);
        if ($targets === []) {
            # Nothing to carry the attribute (not a test class here); leave it untouched.
            return false;
        }

        foreach ($targets as $method) {
            $this->methodHasKind($method, self::TESTO, self::PHPUNIT) or $method->attrGroups[] = new AttributeGroup([
                new Attribute(new FullyQualified(self::PHPUNIT)),
            ]);
        }

        $this->removeClassAttribute($class);

        return true;
    }

    private function hasClassAttribute(Class_ $class): bool
    {
        foreach ($class->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isName($attr->name, self::TESTO)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The groups are trimmed in place: a fresh `AttributeGroup` carries no line number and poisons
     * the class's own, which the scope refresher then chokes on.
     */
    private function removeClassAttribute(Class_ $class): void
    {
        $kept = [];
        foreach ($class->attrGroups as $attrGroup) {
            $attrGroup->attrs = \array_values(\array_filter(
                $attrGroup->attrs,
                fn(Attribute $attr): bool => !$this->isName($attr->name, self::TESTO),
            ));
            $attrGroup->attrs === [] or $kept[] = $attrGroup;
        }

        $class->attrGroups = $kept;
    }

    /**
     * The methods a class-level attribute should fan out to, mirroring Testo discovery: the
     * `#[Test]`-marked methods when any exist (Testo's or the converted PHPUnit form), otherwise —
     * under a class-level `#[\Testo\Test]` — every public, non-static, void/never, non-lifecycle method.
     *
     * @return list<ClassMethod>
     */
    private function testMethods(Class_ $class): array
    {
        $marked = [];
        foreach ($class->getMethods() as $method) {
            if ($this->methodHasKind($method, 'Testo\\Test', 'PHPUnit\\Framework\\Attributes\\Test')) {
                $marked[] = $method;
            }
        }
        if ($marked !== []) {
            return $marked;
        }

        foreach ($class->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isName($attr->name, 'Testo\\Test')) {
                    return \array_values(\array_filter(
                        $class->getMethods(),
                        fn(ClassMethod $method): bool => $this->isDiscoverableByClassLevelTest($method),
                    ));
                }
            }
        }

        return [];
    }

    /**
     * Mirrors Testo's locator for a class-level `#[Test]`.
     */
    private function isDiscoverableByClassLevelTest(ClassMethod $method): bool
    {
        if (!$method->isPublic() || $method->isStatic() || $this->isLifecycleMethod($method)) {
            return false;
        }

        $returnType = $method->returnType;

        return $returnType instanceof Identifier && \in_array($returnType->toLowerString(), ['void', 'never'], true);
    }

    private function isLifecycleMethod(ClassMethod $method): bool
    {
        if (\in_array(\strtolower((string) $this->getName($method)), self::LIFECYCLE_NAMES, true)) {
            return true;
        }

        return $this->methodHasKind($method, ...self::LIFECYCLE_ATTRIBUTES);
    }

    private function methodHasKind(ClassMethod $method, string ...$names): bool
    {
        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                foreach ($names as $name) {
                    if ($this->isName($attr->name, $name)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
