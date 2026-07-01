<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Modifiers;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Turns a Testo test class into a PHPUnit one: attaches `extends \PHPUnit\Framework\TestCase`
 * and reconciles Testo's attribute discovery with PHPUnit's.
 *
 * Only a Testo test class that **extends nothing** is converted (a class that already extends
 * a base class is left untouched — single inheritance would clash, and the base is the right
 * place to convert). The class must carry `#[\Testo\Test]` at class or method level.
 *
 *   - Class-level `#[\Testo\Test]` (every public void/never method is a test) — the marker is
 *     removed and each public, non-static method whose return type is `void`/`never` gains a
 *     `#[\PHPUnit\Framework\Attributes\Test]` attribute. Static methods and methods returning
 *     anything else (data providers/helpers) are skipped, exactly as Testo's locator skips them.
 *   - Method-level `#[\Testo\Test]` — each is renamed in place to
 *     `#[\PHPUnit\Framework\Attributes\Test]`. A `static` test method is also made an instance
 *     method (Testo invokes tests statically or on an instance; PHPUnit only ever calls them on an
 *     instance and flags a `static` test), which is behaviour-preserving since a static body has no
 *     `$this`.
 *
 * Residual: methods are NOT renamed (no `test` prefix is added — PHPUnit discovers the
 * `#[Test]`-attributed methods regardless of name).
 */
#[TestRectorFixtures('TestClassToTestCaseRector')]
final class TestClassToTestCaseRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add `extends \\PHPUnit\\Framework\\TestCase` and convert Testo `#[\\Testo\\Test]` discovery into per-method PHPUnit `#[Test]` attributes',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\Testo\Test]
                        final class MyTest
                        {
                            public function itWorks(): void {}
                        }
                        PHP,
                    <<<'PHP'
                        final class MyTest extends \PHPUnit\Framework\TestCase
                        {
                            #[\PHPUnit\Framework\Attributes\Test]
                            public function itWorks(): void {}
                        }
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
        # Only classes that extend nothing — avoid a single-inheritance clash.
        if ($node->extends !== null) {
            return null;
        }

        $classLevel = $this->extractClassLevelTestAttribute($node);
        $hasMethodLevel = $this->hasMethodLevelTestAttribute($node);

        if (!$classLevel && !$hasMethodLevel) {
            return null;
        }

        $node->extends = new FullyQualified('PHPUnit\\Framework\\TestCase');

        if ($classLevel) {
            foreach ($node->getMethods() as $method) {
                $this->isDiscoverableByClassLevelTest($method) and $this->addPhpUnitTestAttribute($method);
            }
        }

        if ($hasMethodLevel) {
            foreach ($node->getMethods() as $method) {
                $this->convertMethodLevelTestAttribute($method);
            }
        }

        return $node;
    }

    /**
     * Removes a class-level `#[\Testo\Test]` attribute, returning whether one was present.
     */
    private function extractClassLevelTestAttribute(Class_ $class): bool
    {
        $found = false;
        $keptGroups = [];

        foreach ($class->attrGroups as $attrGroup) {
            $keptAttrs = [];
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isName($attr->name, 'Testo\\Test')) {
                    $found = true;
                    continue;
                }
                $keptAttrs[] = $attr;
            }
            $keptAttrs === [] or $keptGroups[] = new AttributeGroup($keptAttrs);
        }

        $found and $class->attrGroups = $keptGroups;

        return $found;
    }

    private function hasMethodLevelTestAttribute(Class_ $class): bool
    {
        foreach ($class->getMethods() as $method) {
            foreach ($method->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    if ($this->isName($attr->name, 'Testo\\Test')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Lifecycle hooks must never be turned into tests: Testo's locator excludes them from the test
     * set, and a method named setUp/tearDown carrying #[Test] would be run by PHPUnit both as a hook
     * (by name) and as a test. Match the lifecycle attribute (Testo's, or its already-converted
     * PHPUnit form) or a reserved PHPUnit lifecycle name.
     *
     * @var list<non-empty-string>
     */
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

    /**
     * Mirrors Testo's locator: a public, non-static method with a `void`/`never` return type that is
     * not a lifecycle hook.
     */
    private function isDiscoverableByClassLevelTest(ClassMethod $method): bool
    {
        if (!$method->isPublic() || $method->isStatic() || $this->isLifecycleMethod($method)) {
            return false;
        }

        # Untyped or complex (nullable/union/intersection) return types are not the
        # plain void/never methods Testo discovers from a class-level marker.
        $returnType = $method->returnType;
        if (!$returnType instanceof Identifier) {
            return false;
        }

        return \in_array($returnType->toLowerString(), ['void', 'never'], true);
    }

    private function isLifecycleMethod(ClassMethod $method): bool
    {
        if (\in_array(\strtolower((string) $this->getName($method)), self::LIFECYCLE_NAMES, true)) {
            return true;
        }

        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                foreach (self::LIFECYCLE_ATTRIBUTES as $lifecycle) {
                    if ($this->isName($attr->name, $lifecycle)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function convertMethodLevelTestAttribute(ClassMethod $method): void
    {
        $converted = false;
        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isName($attr->name, 'Testo\\Test')) {
                    $attr->name = new FullyQualified('PHPUnit\\Framework\\Attributes\\Test');
                    $converted = true;
                }
            }
        }

        # A Testo test method may be declared `static` (Testo invokes it with no instance). PHPUnit
        # instantiates the case and calls the test on that instance, and flags a `static` #[Test]
        # method as unsupported. Drop `static` so it runs idiomatically — a static method body cannot
        # use `$this`, so making it an instance method never changes behaviour. Data providers keep
        # their `static` (PHPUnit requires it): they carry no #[Test], so are never touched here.
        if ($converted && $method->isStatic()) {
            $method->flags &= ~Modifiers::STATIC;
        }
    }

    private function addPhpUnitTestAttribute(ClassMethod $method): void
    {
        # Idempotent: skip a method that already carries the PHPUnit attribute.
        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isName($attr->name, 'PHPUnit\\Framework\\Attributes\\Test')) {
                    return;
                }
            }
        }

        $method->attrGroups[] = new AttributeGroup([
            new Attribute(new FullyQualified('PHPUnit\\Framework\\Attributes\\Test')),
        ]);
    }
}
